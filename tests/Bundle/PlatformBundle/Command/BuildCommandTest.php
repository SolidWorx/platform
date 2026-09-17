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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Command;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Build\AppArchiver;
use SolidWorx\Platform\PlatformBundle\Build\Preflight;
use SolidWorx\Platform\PlatformBundle\Build\StaticBuilder;
use SolidWorx\Platform\PlatformBundle\Build\VersionResolver;
use SolidWorx\Platform\PlatformBundle\Command\BuildCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use function glob;

#[CoversClass(BuildCommand::class)]
final class BuildCommandTest extends TestCase
{
    private string $dir;

    private Filesystem $filesystem;

    #[Override]
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->dir = sys_get_temp_dir() . '/platform-command-' . bin2hex(random_bytes(6));
        $this->filesystem->dumpFile($this->dir . '/vendor/autoload_runtime.php', '<?php');
        $this->filesystem->dumpFile($this->dir . '/public/build/manifest.json', '{}');
        $this->filesystem->dumpFile($this->dir . '/src/App.php', '<?php');
        $this->filesystem->dumpFile(
            $this->dir . '/source/build-static.sh',
            "#!/bin/bash\ndefaultExtensions=\"bcmath,intl\"\n",
        );
        $this->filesystem->dumpFile($this->dir . '/source/app.go', 'package main');
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->filesystem->remove($this->dir);
    }

    public function testDryRunReportsWithoutBuilding(): void
    {
        $tester = $this->tester();

        $tester->execute([
            '--dry-run' => true,
            '--app-version' => 'v9.9.9',
        ]);

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();

        self::assertStringContainsString('v9.9.9', $output);
        self::assertStringContainsString('acme-', $output);
        self::assertStringContainsString('Dry run', $output);
        self::assertFileDoesNotExist($this->dir . '/out');
    }

    public function testDryRunStillWritesTheArchive(): void
    {
        $this->tester()->execute([
            '--dry-run' => true,
        ]);

        self::assertFileExists($this->dir . '/work/frankenphp/app.tar.gz');
        self::assertFileExists($this->dir . '/work/frankenphp/app_checksum.txt');
    }

    public function testMissingToolStopsTheBuild(): void
    {
        $tester = $this->tester(missingTools: ['go']);

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('go is not installed', $tester->getDisplay());
    }

    public function testMissingApplicationDependenciesStopTheBuild(): void
    {
        $this->filesystem->remove($this->dir . '/vendor');

        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('Dependencies are not installed', $tester->getDisplay());
    }

    public function testSkipChecksBypassesApplicationChecks(): void
    {
        $this->filesystem->remove($this->dir . '/vendor');

        $tester = $this->tester();

        $tester->execute([
            '--dry-run' => true,
            '--skip-checks' => true,
        ]);
        $tester->assertCommandIsSuccessful();
    }

    public function testOutputOptionOverridesTheConfiguredOutputDirectory(): void
    {
        $tester = $this->tester();

        $tester->execute([
            '--dry-run' => true,
            '--output' => $this->dir . '/custom-out',
        ]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString($this->dir . '/custom-out/acme-', $tester->getDisplay());
    }

    public function testPhpVersionOptionOverridesTheConfiguredPhpVersion(): void
    {
        $tester = $this->tester();

        $tester->execute([
            '--dry-run' => true,
            '--php-version' => '8.4',
        ]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('PHP_VERSION=8.4', $tester->getDisplay());
    }

    public function testDryRunPrintsTheResolvedEnvironmentAndCommand(): void
    {
        $tester = $this->tester();

        $tester->execute([
            '--dry-run' => true,
        ]);

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();

        self::assertStringContainsString('build-static.sh', $output);
        self::assertStringContainsString('Environment', $output);
        self::assertStringContainsString('PLATFORM_APP_NAME=Acme', $output);
    }

    public function testFinalizedBinaryPassesTheSmokeTestAndSucceeds(): void
    {
        $this->writeFakeBuildScript('v9.9.9');

        $tester = $this->tester();
        $exitCode = $tester->execute([
            '--app-version' => 'v9.9.9',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $tester->getDisplay();
        self::assertStringContainsString('Version   v9.9.9', $output);
        self::assertFileExists($this->findProducedBinary());
    }

    public function testUnverifiedBinaryFailsTheCommandButKeepsTheFile(): void
    {
        $this->writeFakeBuildScript('v0.0.0-does-not-match');

        $tester = $this->tester();
        $exitCode = $tester->execute([
            '--app-version' => 'v9.9.9',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('unverified', $tester->getDisplay());
        self::assertFileExists($this->findProducedBinary(), 'an unverified binary must be kept, not deleted');
    }

    public function testWarningsDoNotBlockTheBuild(): void
    {
        // Preflight reports dev dependencies as a warning, not an error, whenever
        // vendor/composer/installed.json marks them installed — the ordinary state of a
        // developer's first build, so the command must proceed rather than fail.
        $this->filesystem->dumpFile($this->dir . '/vendor/composer/installed.json', '{"dev": true}');

        $tester = $this->tester();

        $tester->execute([
            '--dry-run' => true,
        ]);

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();

        self::assertStringContainsString('Development dependencies will be embedded', $output);
        self::assertStringContainsString('Dry run', $output);
    }

    /**
     * A stand-in for build-static.sh that finishes near-instantly instead of taking 30-60 minutes:
     * it fabricates the xcaddy install marker StaticBuilder looks for and, in the same run, writes
     * a "binary" at the exact path StaticBuilder expects — a tiny script that just echoes
     * $reportedVersion when run, which is all BuildCommand::smokeTest() actually checks.
     *
     * The xcaddy pkgroot directory and the produced binary name are deliberately keyed off
     * *different* forms of the OS name, because StaticBuilder::build() itself mixes them: the
     * xcaddy path is built from the raw `strtolower(php_uname('s'))` (e.g. "darwin"), while the
     * binary path uses hostPlatform()'s mapped value (e.g. "mac"). Collapsing both to the mapped
     * name here would put the xcaddy stub somewhere StaticBuilder never looks, so the bootstrap
     * step would "fail" every time regardless of what this test is trying to exercise.
     */
    private function writeFakeBuildScript(string $reportedVersion): void
    {
        $this->filesystem->dumpFile($this->dir . '/source/xcaddy', "#!/usr/bin/env bash\necho stub\n");

        $this->filesystem->dumpFile(
            $this->dir . '/source/build-static.sh',
            "#!/bin/bash\n"
            . "defaultExtensions=\"bcmath,intl\"\n"
            . "defaultExtensionLibs=\"brotli\"\n"
            . "rawOs=\$(uname -s | tr '[:upper:]' '[:lower:]')\n"
            . "os=\"\${rawOs}\"\n"
            . "if [ \"\${os}\" = \"darwin\" ]; then os=\"mac\"; fi\n"
            . "arch=\$(uname -m)\n"
            . "case \"\${arch}\" in\n"
            . "    arm64|aarch64) spcArch=\"aarch64\" ;;\n"
            . "    x86_64|amd64) spcArch=\"x86_64\" ;;\n"
            . "    *) spcArch=\"\${arch}\" ;;\n"
            . "esac\n"
            . "mkdir -p \"dist/static-php-cli/pkgroot/\${spcArch}-\${rawOs}/go-xcaddy/bin\"\n"
            . "touch \"dist/static-php-cli/pkgroot/\${spcArch}-\${rawOs}/go-xcaddy/bin/xcaddy\"\n"
            . "mkdir -p dist\n"
            . "printf '#!/bin/bash\\necho \"{$reportedVersion}\"\\n' > \"dist/frankenphp-\${os}-\${arch}\"\n"
            . "chmod +x \"dist/frankenphp-\${os}-\${arch}\"\n",
        );
    }

    private function findProducedBinary(): string
    {
        $matches = glob($this->dir . '/out/acme-*');
        self::assertNotFalse($matches);
        self::assertCount(1, $matches);

        return $matches[0];
    }

    /**
     * @param list<string> $missingTools
     */
    private function tester(array $missingTools = []): CommandTester
    {
        $command = new BuildCommand(
            new Preflight($this->finder($missingTools)),
            new VersionResolver(),
            new AppArchiver($this->filesystem),
            new StaticBuilder($this->filesystem, $this->dir . '/source'),
            $this->dir,
            $this->config(),
        );

        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($command);
    }

    /**
     * @param list<string> $missing
     */
    private function finder(array $missing): ExecutableFinder
    {
        return new class($missing) extends ExecutableFinder {
            /**
             * @param list<string> $missing
             */
            public function __construct(
                private readonly array $missing
            ) {
            }

            /**
             * @param array<array-key, mixed> $extraDirs
             */
            public function find(string $name, ?string $default = null, array $extraDirs = []): ?string
            {
                return in_array($name, $this->missing, true) ? null : '/usr/bin/' . $name;
            }
        };
    }

    /**
     * @return array<string, mixed>
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
            'exclude' => ['node_modules/'],
            'hooks' => [
                'install_check' => null,
                'on_boot' => ['cache:clear'],
            ],
        ];
    }
}
