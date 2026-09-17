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

    public function testToolVersionsOmitsMissingToolWithoutAffectingOthers(): void
    {
        $versions = (new Preflight($this->finderWithPaths([
            'git' => $this->stubScript('echo "git version 2.43.0"'),
            'composer' => $this->stubScript('echo "Composer version 2.7.1"'),
            // 'go' is deliberately absent from the map, so the finder reports it missing.
        ])))->toolVersions();

        self::assertArrayHasKey('git', $versions);
        self::assertArrayHasKey('composer', $versions);
        self::assertArrayNotHasKey('go', $versions);
    }

    public function testToolVersionsOmitsToolWhoseVersionCommandFails(): void
    {
        $versions = (new Preflight($this->finderWithPaths([
            'git' => $this->stubScript('echo "git version 2.43.0"'),
            'curl' => $this->stubScript('exit 1'),
        ])))->toolVersions();

        self::assertArrayHasKey('git', $versions);
        self::assertArrayNotHasKey('curl', $versions);
    }

    public function testToolVersionsReducesMultiLineOutputToFirstLine(): void
    {
        $versions = (new Preflight($this->finderWithPaths([
            'jq' => $this->stubScript(<<<'SCRIPT'
                echo "jq-1.7.1"
                echo "some second line the caller should never see"
                SCRIPT),
        ])))->toolVersions();

        self::assertSame('jq-1.7.1', $versions['jq']);
    }

    public function testToolVersionsUsesVersionSubcommandForGo(): void
    {
        // Mirrors the real `go` binary: it rejects --version outright but answers to `version`.
        $versions = (new Preflight($this->finderWithPaths([
            'go' => $this->stubScript(<<<'SCRIPT'
                if [ "$1" = "version" ]; then
                    echo "go version go1.23.4 darwin/arm64"
                    exit 0
                fi
                echo "flag provided but not defined: -version" >&2
                exit 2
                SCRIPT),
        ])))->toolVersions();

        self::assertSame('go version go1.23.4 darwin/arm64', $versions['go']);
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

    /**
     * @param array<string, string> $paths Tool name to the executable path reported for it; a tool
     *                                     absent from this map is reported missing.
     */
    private function finderWithPaths(array $paths): ExecutableFinder
    {
        return new class($paths) extends ExecutableFinder {
            /**
             * @param array<string, string> $paths
             */
            public function __construct(
                private readonly array $paths
            ) {
            }

            /**
             * @param array<array-key, mixed> $extraDirs
             */
            public function find(string $name, ?string $default = null, array $extraDirs = []): ?string
            {
                return $this->paths[$name] ?? null;
            }
        };
    }

    /**
     * Writes an executable shell script into the test's temp directory and returns its path, so a
     * spawned `--version` (or equivalent) process behaves deterministically instead of depending on
     * whatever happens to be installed on the machine running the test.
     */
    private function stubScript(string $body): string
    {
        $path = $this->dir . '/stub-' . bin2hex(random_bytes(6)) . '.sh';
        $this->filesystem->dumpFile($path, "#!/bin/sh\n" . $body . "\n");
        $this->filesystem->chmod($path, 0o755);

        return $path;
    }
}
