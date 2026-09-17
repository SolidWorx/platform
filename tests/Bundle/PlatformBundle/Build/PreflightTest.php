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

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Build\Preflight;
use SolidWorx\Platform\PlatformBundle\Build\Problem;
use SolidWorx\Platform\PlatformBundle\Build\Severity;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;

#[CoversClass(Preflight::class)]
#[CoversClass(Problem::class)]
final class PreflightTest extends TestCase
{
    private string $dir;

    private Filesystem $filesystem;

    #[Override]
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->dir = sys_get_temp_dir() . '/platform-preflight-' . bin2hex(random_bytes(6));
        $this->filesystem->mkdir($this->dir);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->filesystem->remove($this->dir);
    }

    public function testReportsEveryMissingTool(): void
    {
        $problems = (new Preflight($this->finderMissing('go', 'jq')))->run($this->dir, skipAppChecks: true);

        $titles = array_map(static fn (Problem $problem): string => $problem->title, $problems);

        self::assertContains('go is not installed', $titles);
        self::assertContains('jq is not installed', $titles);
        self::assertCount(2, $problems);
    }

    public function testMissingToolIsAnError(): void
    {
        $problems = (new Preflight($this->finderMissing('go')))->run($this->dir, skipAppChecks: true);

        self::assertSame(Severity::Error, $problems[0]->severity);
        self::assertTrue($problems[0]->isError());
        self::assertStringContainsString('brew install go', $problems[0]->detail);
    }

    public function testNoProblemsWhenEverythingIsPresent(): void
    {
        $this->prepareApplication();

        self::assertSame([], (new Preflight($this->finderMissing()))->run($this->dir, skipAppChecks: false));
    }

    public function testMissingVendorIsAnError(): void
    {
        $problems = (new Preflight($this->finderMissing()))->run($this->dir, skipAppChecks: false);

        $titles = array_map(static fn (Problem $problem): string => $problem->title, $problems);

        self::assertContains('Dependencies are not installed', $titles);
    }

    public function testMissingAssetsIsAnError(): void
    {
        $this->prepareApplication();
        $this->filesystem->remove($this->dir . '/public/build/manifest.json');

        $problems = (new Preflight($this->finderMissing()))->run($this->dir, skipAppChecks: false);

        $titles = array_map(static fn (Problem $problem): string => $problem->title, $problems);

        self::assertContains('Frontend assets are not built', $titles);
    }

    public function testDevDependenciesAreOnlyAWarning(): void
    {
        $this->prepareApplication(dev: true);

        $problems = (new Preflight($this->finderMissing()))->run($this->dir, skipAppChecks: false);

        self::assertCount(1, $problems);
        self::assertSame(Severity::Warning, $problems[0]->severity);
        self::assertFalse($problems[0]->isError());
        self::assertSame('Development dependencies will be embedded', $problems[0]->title);
    }

    public function testApplicationChecksAreSkippable(): void
    {
        self::assertSame([], (new Preflight($this->finderMissing()))->run($this->dir, skipAppChecks: true));
    }

    private function prepareApplication(bool $dev = false): void
    {
        $this->filesystem->dumpFile($this->dir . '/vendor/autoload_runtime.php', '<?php');
        $this->filesystem->dumpFile($this->dir . '/public/build/manifest.json', '{}');
        $this->filesystem->dumpFile(
            $this->dir . '/vendor/composer/installed.json',
            json_encode([
                'packages' => [],
                'dev' => $dev,
            ], JSON_THROW_ON_ERROR),
        );
    }

    private function finderMissing(string ...$missing): ExecutableFinder
    {
        return new class(array_values($missing)) extends ExecutableFinder {
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
}
