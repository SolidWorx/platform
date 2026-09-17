<?php

declare(strict_types=1);

/*
 * This file is part of SolidWorx Platform project.
 *
 * (c) Pierre du Plessis <open-source@solidworx.co>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Build;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SolidWorx\Platform\PlatformBundle\Build\BuildOptions;
use SolidWorx\Platform\PlatformBundle\Build\StaticBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @phpstan-import-type BuildConfig from BuildOptions
 */
#[CoversClass(StaticBuilder::class)]
final class StaticBuilderTest extends TestCase
{
    private string $dir;

    private string $sourceDir;

    private Filesystem $filesystem;

    #[Override]
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->dir = sys_get_temp_dir() . '/platform-builder-' . bin2hex(random_bytes(6));
        $this->sourceDir = $this->dir . '/source';

        $this->filesystem->dumpFile($this->sourceDir . '/app.go', 'package main');
        $this->filesystem->dumpFile($this->sourceDir . '/internal/serverconfig/server_name.go', 'package serverconfig');
        $this->filesystem->dumpFile(
            $this->sourceDir . '/build-static.sh',
            "#!/bin/bash\ndefaultExtensions=\"bcmath,intl,ssh2\"\ndefaultExtensionLibs=\"brotli\"\n",
        );
        // The real vendored Resources/build directory (Task 5) ships this alongside
        // build-static.sh; stage() chmods it unconditionally, so the fixture needs one too.
        $this->filesystem->dumpFile($this->sourceDir . '/xcaddy', "#!/usr/bin/env bash\necho stub\n");
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->filesystem->remove($this->dir);
    }

    public function testStageCopiesSourcesIntoTheWorkDirectory(): void
    {
        $path = $this->builder()->stage($this->options());

        self::assertSame($this->dir . '/work/frankenphp', $path);
        self::assertFileExists($path . '/app.go');
        self::assertFileExists($path . '/internal/serverconfig/server_name.go');
        self::assertFileExists($path . '/build-static.sh');
    }

    public function testStageLeavesUnchangedFilesAlone(): void
    {
        $builder = $this->builder();
        $path = $builder->stage($this->options());

        touch($path . '/app.go', time() - 3600);
        $before = filemtime($path . '/app.go');

        $builder->stage($this->options());

        self::assertSame($before, filemtime($path . '/app.go'));
    }

    public function testStageRefreshesChangedFiles(): void
    {
        $builder = $this->builder();
        $path = $builder->stage($this->options());

        $this->filesystem->dumpFile($this->sourceDir . '/app.go', 'package main // changed');
        $builder->stage($this->options());

        self::assertStringContainsString('changed', (string) file_get_contents($path . '/app.go'));
    }

    public function testDefaultExtensionsAreParsedFromTheScript(): void
    {
        self::assertSame(['bcmath', 'intl', 'ssh2'], $this->builder()->defaultExtensions());
    }

    public function testEnvironmentExportsAnEmptyExtensionsValueWhenComposerShouldDecide(): void
    {
        $environment = $this->builder()->environment($this->options(), '/app');

        self::assertArrayHasKey('PHP_EXTENSIONS', $environment);
        self::assertSame('', $environment['PHP_EXTENSIONS']);
        self::assertSame('/app', $environment['EMBED']);
        self::assertSame('8.5', $environment['PHP_VERSION']);
        self::assertSame('libavif', $environment['PHP_EXTENSION_LIBS']);
        self::assertSame('v2.4.0', $environment['FRANKENPHP_VERSION']);
    }

    public function testEnvironmentExportsPhpVersionAndPhpExtensionsEvenWhenUnconfigured(): void
    {
        $config = $this->config();
        $config['php']['version'] = null;

        $environment = $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), '/app');

        // Both keys must always be present, even empty: build-static.sh treats an empty value the
        // same as an unset one, but only an explicit empty string stops a PHP_VERSION or
        // PHP_EXTENSIONS a developer's shell or CI image happens to export from bleeding through
        // Symfony Process's inherited environment.
        self::assertArrayHasKey('PHP_VERSION', $environment);
        self::assertSame('', $environment['PHP_VERSION']);
        self::assertArrayHasKey('PHP_EXTENSIONS', $environment);
        self::assertSame('', $environment['PHP_EXTENSIONS']);
    }

    public function testEnvironmentResolvesExtensionsWhenTheyAreCustomised(): void
    {
        $config = $this->config();
        $config['php']['remove'] = ['ssh2'];

        $environment = $this->builder()->environment(
            BuildOptions::fromConfig($config, 'v2.4.0', []),
            '/app',
        );

        self::assertSame('bcmath,intl', $environment['PHP_EXTENSIONS']);
    }

    public function testEnvironmentCarriesTheApplicationIdentityForTheShim(): void
    {
        $environment = $this->builder()->environment($this->options(), '/app');

        self::assertSame('Acme', $environment['PLATFORM_APP_NAME']);
        self::assertSame('ACME', $environment['PLATFORM_APP_ENV_PREFIX']);
        self::assertSame('9000', $environment['PLATFORM_APP_PORT']);
        self::assertSame('acme:is-installed', $environment['PLATFORM_APP_INSTALL_CHECK']);
        self::assertSame('cache:clear,acme:warm', $environment['PLATFORM_APP_BOOT_COMMANDS']);
        self::assertSame($this->dir . '/work/frankenphp', $environment['PLATFORM_APP_SOURCE_DIR']);
    }

    public function testHostPlatformUsesTheScriptsNaming(): void
    {
        $platform = $this->builder()->hostPlatform();

        self::assertContains($platform['os'], ['mac', 'linux']);
        self::assertNotSame('', $platform['arch']);
    }

    public function testBuildRemovesStagedDistBeforeRunningAndNeverExportsCleanToTheScript(): void
    {
        $builder = $this->builder();
        $options = $this->options();
        $staged = $this->dir . '/work/frankenphp';

        // A stale dist/ from a previous run, the way a warm work directory would have one.
        $this->filesystem->mkdir($staged . '/dist');
        $this->filesystem->dumpFile($staged . '/dist/marker.txt', 'stale build output');

        // A stand-in for the real build-static.sh: instant, and it lets the test observe whether
        // CLEAN reached it by leaving a file behind if it did. It never installs a real xcaddy,
        // so build() is expected to give up right after the bootstrap attempt below — that
        // failure is not what this test is about.
        $this->filesystem->dumpFile(
            $this->sourceDir . '/build-static.sh',
            "#!/bin/bash\n"
            . "defaultExtensions=\"bcmath,intl,ssh2\"\n"
            . "defaultExtensionLibs=\"brotli\"\n"
            . "if [ -n \"\${CLEAN:-}\" ]; then\n"
            . "    mkdir -p dist\n"
            . "    touch dist/CLEAN_WAS_SET\n"
            . "fi\n",
        );

        try {
            $builder->build($options, '/app', static function (string $output): void {
            }, clean: true);
        } catch (RuntimeException) {
            // Expected — see above.
        }

        self::assertFileDoesNotExist($staged . '/dist/marker.txt', 'the stale dist/ was not removed before build-static.sh ran');
        self::assertFileDoesNotExist($staged . '/dist/CLEAN_WAS_SET', 'CLEAN reached build-static.sh');
    }

    public function testBuildLeavesStagedDistAloneWhenNotCleaning(): void
    {
        $builder = $this->builder();
        $options = $this->options();
        $staged = $this->dir . '/work/frankenphp';

        $this->filesystem->mkdir($staged . '/dist');
        $this->filesystem->dumpFile($staged . '/dist/marker.txt', 'kept build output');

        try {
            $builder->build($options, '/app', static function (string $output): void {
            }, clean: false);
        } catch (RuntimeException) {
            // Expected — the fixture's build-static.sh never installs a real xcaddy.
        }

        self::assertFileExists($staged . '/dist/marker.txt');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeLdflagsCharacters(): iterable
    {
        yield 'apostrophe' => ["'"];
        yield 'double quote' => ['"'];
        yield 'backslash' => ['\\'];
        yield 'newline' => ["\n"];
        yield 'carriage return' => ["\r"];
    }

    #[DataProvider('unsafeLdflagsCharacters')]
    public function testEnvironmentRejectsADescriptionThatWouldBreakLdflagsQuoting(string $character): void
    {
        $config = $this->config();
        $config['description'] = 'Acme does' . $character . ' things';

        $this->expectException(InvalidArgumentException::class);

        $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), '/app');
    }

    public function testEnvironmentExceptionNamesTheOffendingKeyAndValue(): void
    {
        $config = $this->config();
        $config['description'] = "Pierre's invoicing app";

        try {
            $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), '/app');
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('description', $exception->getMessage());
            self::assertStringContainsString("Pierre's invoicing app", $exception->getMessage());
        }
    }

    public function testEnvironmentRejectsAnApostropheInTheAppName(): void
    {
        $config = $this->config();
        $config['name'] = "Acme's";

        $this->expectException(InvalidArgumentException::class);

        $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), '/app');
    }

    public function testEnvironmentRejectsAnApostropheInTheEnvPrefix(): void
    {
        $config = $this->config();
        $config['env_prefix'] = "AC'ME";

        $this->expectException(InvalidArgumentException::class);

        $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), '/app');
    }

    public function testEnvironmentRejectsAnApostropheInTheDefaultPort(): void
    {
        $config = $this->config();
        $config['default_port'] = "9000'";

        $this->expectException(InvalidArgumentException::class);

        $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), '/app');
    }

    public function testEnvironmentRejectsAnApostropheInTheInstallCheckCommand(): void
    {
        $config = $this->config();
        $config['hooks']['install_check'] = "acme:is-installed's-check";

        $this->expectException(InvalidArgumentException::class);

        $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), '/app');
    }

    public function testEnvironmentRejectsAnApostropheInABootCommand(): void
    {
        $config = $this->config();
        $config['hooks']['on_boot'] = ["acme:warm's-cache"];

        $this->expectException(InvalidArgumentException::class);

        $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), '/app');
    }

    public function testEnvironmentRejectsAnApostropheInTheVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->builder()->environment(BuildOptions::fromConfig($this->config(), "v2.4.0's-tag", []), '/app');
    }

    public function testEnvironmentAllowsOrdinaryPunctuation(): void
    {
        $config = $this->config();
        $config['description'] = 'Acme does things - fast, reliably & well.';

        $environment = $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), '/app');

        self::assertSame('Acme does things - fast, reliably & well.', $environment['PLATFORM_APP_DESCRIPTION']);
    }

    private function builder(): StaticBuilder
    {
        return new StaticBuilder($this->filesystem, $this->sourceDir);
    }

    private function options(): BuildOptions
    {
        return BuildOptions::fromConfig($this->config(), 'v2.4.0', []);
    }

    /**
     * @return BuildConfig
     */
    private function config(): array
    {
        return [
            'name' => 'Acme',
            'description' => 'Acme does things',
            'binary_name' => 'acme',
            'env_prefix' => 'ACME',
            'default_port' => '9000',
            'output_dir' => $this->dir . '/out',
            'work_dir' => $this->dir . '/work',
            'php' => [
                'version' => '8.5',
                'extensions' => [],
                'add' => [],
                'remove' => [],
                'extension_libs' => ['libavif'],
            ],
            'exclude' => [],
            'hooks' => [
                'install_check' => 'acme:is-installed',
                'on_boot' => ['cache:clear', 'acme:warm'],
            ],
        ];
    }
}
