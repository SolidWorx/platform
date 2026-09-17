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
use RuntimeException;
use SolidWorx\Platform\PlatformBundle\Build\AppArchiver;
use SolidWorx\Platform\PlatformBundle\Build\Archive;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[CoversClass(AppArchiver::class)]
#[CoversClass(Archive::class)]
final class AppArchiverTest extends TestCase
{
    private string $dir;

    private Filesystem $filesystem;

    #[Override]
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->dir = sys_get_temp_dir() . '/platform-archive-' . bin2hex(random_bytes(6));
        $this->filesystem->dumpFile($this->dir . '/src/App.php', '<?php');
        $this->filesystem->dumpFile($this->dir . '/node_modules/left-pad/index.js', 'nope');
        $this->filesystem->dumpFile($this->dir . '/var/cache/prod/container.php', 'nope');
        $this->filesystem->dumpFile($this->dir . '/public/index.php', '<?php');
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->filesystem->remove($this->dir);
    }

    public function testArchiveContainsProjectFiles(): void
    {
        $archive = $this->archive();

        self::assertContains('./src/App.php', $this->entries($archive->path));
        self::assertContains('./public/index.php', $this->entries($archive->path));
    }

    public function testExcludedPathsAreAbsent(): void
    {
        $entries = $this->entries($this->archive()->path);

        self::assertNotContains('./node_modules/left-pad/index.js', $entries);
        self::assertNotContains('./var/cache/prod/container.php', $entries);
    }

    public function testDestinationIsExcludedFromItsOwnArchive(): void
    {
        $archive = $this->archive();

        foreach ($this->entries($archive->path) as $entry) {
            self::assertStringNotContainsString('app.tar.gz', $entry);
        }
    }

    public function testMandatoryExcludesApplyEvenWithNoConfiguration(): void
    {
        $this->filesystem->dumpFile($this->dir . '/.git/config', 'nope');

        $archive = (new AppArchiver($this->filesystem))->archive(
            $this->dir,
            $this->dir . '/var/build/frankenphp/app.tar.gz',
            [],
        );

        $entries = $this->entries($archive->path);

        self::assertNotContains('./.git/config', $entries);
        self::assertNotContains('./node_modules/left-pad/index.js', $entries);
        self::assertNotContains('./var/cache/prod/container.php', $entries);
        self::assertContains('./src/App.php', $entries);
    }

    public function testChecksumFileMatchesTheArchive(): void
    {
        $archive = $this->archive();

        $checksumFile = dirname($archive->path) . '/app_checksum.txt';

        self::assertFileExists($checksumFile);
        self::assertSame($archive->checksum, trim((string) file_get_contents($checksumFile)));
        self::assertSame(hash_file('sha256', $archive->path), $archive->checksum);
    }

    public function testReportsFileCountAndSize(): void
    {
        $archive = $this->archive();

        self::assertSame(2, $archive->fileCount);
        self::assertGreaterThan(0, $archive->bytes);
    }

    public function testThrowsWhenTheProjectDirectoryIsEmpty(): void
    {
        $emptyDir = sys_get_temp_dir() . '/platform-archive-empty-' . bin2hex(random_bytes(6));
        $this->filesystem->mkdir($emptyDir);
        $destination = $emptyDir . '/var/build/frankenphp/app.tar.gz';
        $checksumFile = dirname($destination) . '/app_checksum.txt';

        // Simulates a stale checksum left behind by an earlier successful run in this same
        // (persistent) work_dir.
        $this->filesystem->dumpFile($checksumFile, 'stale-checksum-from-a-previous-run');

        $this->expectException(RuntimeException::class);

        try {
            (new AppArchiver($this->filesystem))->archive($emptyDir, $destination, []);
        } finally {
            self::assertFileDoesNotExist($destination);
            self::assertFileDoesNotExist($checksumFile);

            $this->filesystem->remove($emptyDir);
        }
    }

    public function testThrowsWhenExcludesMatchEverything(): void
    {
        $destination = $this->dir . '/var/build/frankenphp/app.tar.gz';

        $this->expectException(RuntimeException::class);

        try {
            (new AppArchiver($this->filesystem))->archive($this->dir, $destination, ['src/', 'public/']);
        } finally {
            self::assertFileDoesNotExist($destination);
        }
    }

    public function testOutputDirectoryInsideTheProjectIsExcluded(): void
    {
        $outputDir = $this->dir . '/build';
        $this->filesystem->dumpFile($outputDir . '/acme-mac-arm64', 'a previous build\'s binary');

        $archive = (new AppArchiver($this->filesystem))->archive(
            $this->dir,
            $this->dir . '/var/build/frankenphp/app.tar.gz',
            ['node_modules/', 'var/'],
            $outputDir,
        );

        $entries = $this->entries($archive->path);

        self::assertNotContains('./build/acme-mac-arm64', $entries);
        self::assertContains('./src/App.php', $entries);
    }

    public function testOutputDirectoryOutsideTheProjectIsIgnored(): void
    {
        $outputDir = sys_get_temp_dir() . '/platform-archive-output-' . bin2hex(random_bytes(6));
        $this->filesystem->dumpFile($outputDir . '/acme-mac-arm64', 'not part of the project at all');

        try {
            $archive = (new AppArchiver($this->filesystem))->archive(
                $this->dir,
                $this->dir . '/var/build/frankenphp/app.tar.gz',
                ['node_modules/', 'var/'],
                $outputDir,
            );

            $entries = $this->entries($archive->path);

            self::assertContains('./src/App.php', $entries);
            self::assertContains('./public/index.php', $entries);
        } finally {
            $this->filesystem->remove($outputDir);
        }
    }

    public function testOutputDirectorySameAsWorkDirectoryDoesNotBreakTar(): void
    {
        $destination = $this->dir . '/var/build/frankenphp/app.tar.gz';

        $archive = (new AppArchiver($this->filesystem))->archive(
            $this->dir,
            $destination,
            ['node_modules/', 'var/cache/'],
            dirname($destination),
        );

        $entries = $this->entries($archive->path);

        self::assertContains('./src/App.php', $entries);
        self::assertContains('./public/index.php', $entries);
    }

    public function testOutputDirectoryNestedAboveWorkDirectoryDoesNotBreakTar(): void
    {
        // The output directory is the parent of where the archive itself is staged — its exclusion
        // subsumes the destination's own, and tar must tolerate the overlapping --exclude flags.
        $destination = $this->dir . '/var/build/frankenphp/app.tar.gz';

        $archive = (new AppArchiver($this->filesystem))->archive(
            $this->dir,
            $destination,
            ['node_modules/'],
            $this->dir . '/var',
        );

        $entries = $this->entries($archive->path);

        self::assertContains('./src/App.php', $entries);
        self::assertContains('./public/index.php', $entries);
        self::assertNotContains('./var/cache/prod/container.php', $entries);
    }

    private function archive(): Archive
    {
        return (new AppArchiver($this->filesystem))->archive(
            $this->dir,
            $this->dir . '/var/build/frankenphp/app.tar.gz',
            ['node_modules/', 'var/'],
        );
    }

    /**
     * @return list<string>
     */
    private function entries(string $path): array
    {
        $process = new Process(['tar', '-tzf', $path]);
        $process->mustRun();

        return array_values(array_filter(
            explode("\n", trim($process->getOutput())),
            static fn (string $line): bool => ! str_ends_with($line, '/'),
        ));
    }
}
