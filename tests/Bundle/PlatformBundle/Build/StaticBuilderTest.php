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
use SolidWorx\Platform\PlatformBundle\Build\BuildStepFailedException;
use SolidWorx\Platform\PlatformBundle\Build\StaticBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

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

    public function testEnvironmentExportsAnEmptyExtensionsValueWhenTheScriptShouldDecide(): void
    {
        $environment = $this->builder()->environment($this->options(), $this->dir . '/embed');

        self::assertArrayHasKey('PHP_EXTENSIONS', $environment);
        self::assertSame('', $environment['PHP_EXTENSIONS']);
        self::assertSame('8.5', $environment['PHP_VERSION']);
        self::assertSame('libavif', $environment['PHP_EXTENSION_LIBS']);
        self::assertSame('v2.4.0', $environment['FRANKENPHP_VERSION']);
    }

    public function testEnvironmentPointsEmbedAtTheGivenBoundedDirectoryRatherThanTheProject(): void
    {
        // EMBED must never be the project directory itself (Task 3): build-static.sh appends
        // --with-frankenphp-app=${EMBED} to SPC_OPT_BUILD_ARGS unconditionally, so static-php-cli
        // would otherwise be handed the project's own multi-gigabyte work_dir to traverse.
        $embedDir = $this->dir . '/embed';

        $environment = $this->builder()->environment($this->options(), $embedDir);

        self::assertSame($embedDir, $environment['EMBED']);
        self::assertNotSame($this->dir, $environment['EMBED']);
    }

    public function testEnvironmentExportsPhpVersionAndPhpExtensionsEvenWhenUnconfigured(): void
    {
        $config = $this->config();
        $config['php']['version'] = null;

        $environment = $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), $this->dir . '/embed');

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
            $this->dir . '/embed',
        );

        self::assertSame('bcmath,intl', $environment['PHP_EXTENSIONS']);
    }

    public function testEnvironmentCarriesTheApplicationIdentityForTheShim(): void
    {
        $environment = $this->builder()->environment($this->options(), $this->dir . '/embed');

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

        // build() extracts EMBED from this before it ever looks at dist/ or CLEAN — in real usage
        // BuildCommand has already written it by the time build() runs.
        $this->writeStubArchive($staged);

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
            $builder->build($options, static function (string $output): void {
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

        $this->writeStubArchive($staged);

        $this->filesystem->mkdir($staged . '/dist');
        $this->filesystem->dumpFile($staged . '/dist/marker.txt', 'kept build output');

        try {
            $builder->build($options, static function (string $output): void {
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

        $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), $this->dir . '/embed');
    }

    public function testEnvironmentExceptionNamesTheOffendingKeyAndValue(): void
    {
        $config = $this->config();
        $config['description'] = "Pierre's invoicing app";

        try {
            $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), $this->dir . '/embed');
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

        $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), $this->dir . '/embed');
    }

    public function testEnvironmentRejectsAnApostropheInTheEnvPrefix(): void
    {
        $config = $this->config();
        $config['env_prefix'] = "AC'ME";

        $this->expectException(InvalidArgumentException::class);

        $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), $this->dir . '/embed');
    }

    public function testEnvironmentRejectsAnApostropheInTheDefaultPort(): void
    {
        $config = $this->config();
        $config['default_port'] = "9000'";

        $this->expectException(InvalidArgumentException::class);

        $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), $this->dir . '/embed');
    }

    public function testEnvironmentRejectsAnApostropheInTheInstallCheckCommand(): void
    {
        $config = $this->config();
        $config['hooks']['install_check'] = "acme:is-installed's-check";

        $this->expectException(InvalidArgumentException::class);

        $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), $this->dir . '/embed');
    }

    public function testEnvironmentRejectsAnApostropheInABootCommand(): void
    {
        $config = $this->config();
        $config['hooks']['on_boot'] = ["acme:warm's-cache"];

        $this->expectException(InvalidArgumentException::class);

        $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), $this->dir . '/embed');
    }

    public function testEnvironmentRejectsAnApostropheInTheVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->builder()->environment(BuildOptions::fromConfig($this->config(), "v2.4.0's-tag", []), $this->dir . '/embed');
    }

    public function testEnvironmentAllowsOrdinaryPunctuation(): void
    {
        $config = $this->config();
        $config['description'] = 'Acme does things - fast, reliably & well.';

        $environment = $this->builder()->environment(BuildOptions::fromConfig($config, 'v2.4.0', []), $this->dir . '/embed');

        self::assertSame('Acme does things - fast, reliably & well.', $environment['PLATFORM_APP_DESCRIPTION']);
    }

    public function testValidateRejectsUnsafeLdflagsValues(): void
    {
        $config = $this->config();
        $config['name'] = "Acme's";

        $this->expectException(InvalidArgumentException::class);

        $this->builder()->validate(BuildOptions::fromConfig($config, 'v2.4.0', []));
    }

    public function testValidatePassesForSafeValues(): void
    {
        $this->expectNotToPerformAssertions();

        $this->builder()->validate($this->options());
    }

    public function testStageDoesNotCopyTheBuildsOwnStaleOutputOverTheFreshOne(): void
    {
        // Exactly what .gitignore anticipates: a developer once built straight out of the source
        // checkout, leaving these three behind in Resources/build.
        $this->filesystem->dumpFile($this->sourceDir . '/app.tar.gz', 'stale-archive-bytes');
        $this->filesystem->dumpFile($this->sourceDir . '/app_checksum.txt', 'stale-checksum');
        $this->filesystem->dumpFile($this->sourceDir . '/dist/static-php-cli/marker.txt', 'stale build tree');

        $target = $this->builder()->stage($this->options());

        // BuildCommand has already written the real, freshly-built archive and checksum into the
        // staged directory before stage() runs.
        $this->filesystem->dumpFile($target . '/app.tar.gz', 'fresh-archive-bytes');
        $this->filesystem->dumpFile($target . '/app_checksum.txt', 'fresh-checksum');

        $this->builder()->stage($this->options());

        self::assertSame('fresh-archive-bytes', file_get_contents($target . '/app.tar.gz'));
        self::assertSame('fresh-checksum', file_get_contents($target . '/app_checksum.txt'));
        self::assertFileDoesNotExist($target . '/dist/static-php-cli/marker.txt');
    }

    public function testBuildNeutralisesTheInheritedCiVariable(): void
    {
        $builder = $this->builder();
        $options = $this->options();
        $staged = $this->dir . '/work/frankenphp';

        $this->writeStubArchive($staged);

        // build-static.sh:212-215 removes ./downloads and ./source when CI is set — this stand-in
        // instead leaves a marker so the test can observe whether CI reached it.
        $this->filesystem->dumpFile(
            $this->sourceDir . '/build-static.sh',
            "#!/bin/bash\n"
            . "defaultExtensions=\"bcmath,intl,ssh2\"\n"
            . "defaultExtensionLibs=\"brotli\"\n"
            . "if [ -n \"\${CI:-}\" ]; then\n"
            . "    mkdir -p dist\n"
            . "    touch dist/CI_WAS_SET\n"
            . "fi\n",
        );

        putenv('CI=true');

        try {
            $builder->build($options, static function (string $output): void {
            }, clean: false);
        } catch (RuntimeException) {
            // Expected — the fixture never installs a real xcaddy.
        } finally {
            putenv('CI');
        }

        self::assertFileDoesNotExist($staged . '/dist/CI_WAS_SET', 'the inherited CI variable reached build-static.sh');
    }

    public function testBuildWritesTheFailingStepsLogAndNamesItInTheException(): void
    {
        $builder = $this->builder();
        $options = $this->options();
        $staged = $this->dir . '/work/frankenphp';

        $this->writeStubArchive($staged);

        $this->filesystem->dumpFile(
            $this->sourceDir . '/build-static.sh',
            "#!/bin/bash\n"
            . "defaultExtensions=\"bcmath,intl,ssh2\"\n"
            . "defaultExtensionLibs=\"brotli\"\n"
            . "echo 'about to install xcaddy'\n"
            . "echo 'still nothing here' >&2\n",
        );

        try {
            $builder->build($options, static function (string $output): void {
            }, clean: false);
            self::fail('Expected a BuildStepFailedException.');
        } catch (BuildStepFailedException $exception) {
            self::assertSame('bootstrap', $exception->step);
            self::assertSame($this->dir . '/work/log/bootstrap.log', $exception->logPath);
            self::assertFileExists($exception->logPath);
            self::assertStringContainsString('about to install xcaddy', (string) file_get_contents($exception->logPath));
        }
    }

    public function testBuildClearsThePreviousBuildsLogsBeforeStarting(): void
    {
        $builder = $this->builder();
        $options = $this->options();
        $logDir = $this->dir . '/work/log';

        // A log left over from a previous, successful build — must never be readable against a
        // later failure (the same stale-artefact hazard AppArchiver guards against for the
        // archive/checksum pair).
        $this->filesystem->dumpFile($logDir . '/bootstrap.log', 'output from a previous successful build');

        $this->writeStubArchive($this->dir . '/work/frankenphp');

        $this->filesystem->dumpFile(
            $this->sourceDir . '/build-static.sh',
            "#!/bin/bash\n"
            . "defaultExtensions=\"bcmath,intl,ssh2\"\n"
            . "defaultExtensionLibs=\"brotli\"\n"
            . "echo 'this build never wrote the stale line'\n",
        );

        try {
            $builder->build($options, static function (string $output): void {
            }, clean: false);
        } catch (RuntimeException) {
            // Expected — the fixture never installs a real xcaddy.
        }

        self::assertStringNotContainsString(
            'output from a previous successful build',
            (string) file_get_contents($logDir . '/bootstrap.log'),
        );
    }

    public function testBuildClearsThePreviousBuildsGoBuildLogBeforeStarting(): void
    {
        $builder = $this->builder();
        $options = $this->options();
        $staged = $this->dir . '/work/frankenphp';

        // A tee'd log left over from a previous build that did reach the link step — must never
        // be readable against a later, unrelated failure (the same stale-artefact hazard the step
        // logs already guard against).
        $this->filesystem->dumpFile(
            $staged . '/dist/static-php-cli/log/go-build.log',
            'output from a previous successful build',
        );

        $this->writeStubArchive($staged);

        $this->filesystem->dumpFile(
            $this->sourceDir . '/build-static.sh',
            "#!/bin/bash\n"
            . "defaultExtensions=\"bcmath,intl,ssh2\"\n"
            . "defaultExtensionLibs=\"brotli\"\n",
        );

        try {
            $builder->build($options, static function (string $output): void {
            }, clean: false);
        } catch (RuntimeException) {
            // Expected — the fixture never installs a real xcaddy.
        }

        self::assertFileDoesNotExist($staged . '/dist/static-php-cli/log/go-build.log');
    }

    public function testGoBuildLogPathPointsAtWhereTheShimTeesItsOutput(): void
    {
        self::assertSame(
            $this->dir . '/work/frankenphp/dist/static-php-cli/log/go-build.log',
            $this->builder()->goBuildLogPath($this->options()),
        );
    }

    private function writeStubArchive(string $stagedDir): void
    {
        $this->filesystem->mkdir($stagedDir);

        $source = $this->dir . '/archive-source';
        $this->filesystem->dumpFile($source . '/marker.txt', 'stub archive contents');

        new Process(['tar', '-czf', $stagedDir . '/app.tar.gz', '-C', $source, '.'])->mustRun();
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
