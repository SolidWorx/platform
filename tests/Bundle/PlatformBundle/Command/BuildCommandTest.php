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
