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

namespace SolidWorx\Platform\PlatformBundle\Build;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;
use function dirname;
use function explode;
use function filesize;
use function hash_file;
use function is_dir;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function substr;
use function trim;

/**
 * Packs the current checkout into the tarball the binary embeds.
 *
 * The checkout is archived as it stands — no dependency install, no asset build. Whether the tree
 * is production-ready is the caller's business; {@see Preflight} reports on it.
 */
final readonly class AppArchiver
{
    private const string CHECKSUM_FILE = 'app_checksum.txt';

    /**
     * Excluded whatever the application configures. `exclude` replaces the platform's defaults
     * rather than adding to them, so an application that excludes one directory of its own would
     * otherwise start shipping its git history and a stale production container.
     *
     * @var list<string>
     */
    private const array ALWAYS_EXCLUDE = ['.git', 'var/cache', 'var/log', 'node_modules'];

    public function __construct(
        private Filesystem $filesystem,
    ) {
    }

    /**
     * @param list<string> $exclude Paths relative to the project root.
     */
    public function archive(string $projectDir, string $destination, array $exclude): Archive
    {
        $this->filesystem->mkdir(dirname($destination));

        $command = ['tar', '-czvf', $destination, '-C', $projectDir];

        foreach ($this->excludes($projectDir, $destination, $exclude) as $path) {
            $command[] = '--exclude=./' . $path;
        }

        $command[] = '.';

        // No timeout: a large application legitimately takes minutes to compress.
        $process = new Process($command, timeout: null);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'Could not archive the application: %s',
                trim($process->getErrorOutput()) !== '' ? trim($process->getErrorOutput()) : 'tar exited with code ' . $process->getExitCode(),
            ));
        }

        $fileCount = $this->countFiles($projectDir, $process->getErrorOutput() . "\n" . $process->getOutput());

        if ($fileCount === 0) {
            $this->filesystem->remove($destination);

            throw new RuntimeException(sprintf(
                'The application archive built from %s contains no files. Either the project '
                . 'directory is empty, or the configured excludes matched everything in it.',
                $projectDir,
            ));
        }

        $checksum = hash_file('sha256', $destination);

        if ($checksum === false) {
            throw new RuntimeException(sprintf('Could not checksum the application archive at %s.', $destination));
        }

        $this->filesystem->dumpFile(dirname($destination) . '/' . self::CHECKSUM_FILE, $checksum . "\n");

        $size = filesize($destination);

        return new Archive(
            path: $destination,
            checksum: $checksum,
            fileCount: $fileCount,
            bytes: $size === false ? 0 : $size,
        );
    }

    /**
     * The archive's own destination is always excluded: `work_dir` defaults to `var/build`, inside
     * the project, so without this tar reads the file it is in the middle of writing.
     *
     * @param list<string> $exclude
     *
     * @return list<string>
     */
    private function excludes(string $projectDir, string $destination, array $exclude): array
    {
        $excludes = self::ALWAYS_EXCLUDE;

        foreach ($exclude as $path) {
            $excludes[] = trim($path, '/');
        }

        $relative = Path::makeRelative(dirname($destination), $projectDir);

        // An empty or parent-relative path means the destination lives outside the project; tar will
        // never walk into it, so there is nothing to exclude.
        if ($relative !== '' && ! str_starts_with($relative, '..')) {
            $excludes[] = trim($relative, '/');
        }

        return array_values(array_unique($excludes));
    }

    /**
     * `tar -v` lists each entry it writes (bsdtar's `-c` prefixes each line with `a `; GNU tar
     * omits the prefix). Either way, directory entries are counted separately from files: bsdtar's
     * creation listing has no trailing slash to tell them apart the way a `-t` listing does, so
     * each candidate path is stat'd against the source tree instead — one syscall per entry, far
     * cheaper than a second pass over the archive.
     */
    private function countFiles(string $projectDir, string $verboseOutput): int
    {
        $count = 0;

        foreach (explode("\n", trim($verboseOutput)) as $line) {
            if ($line === '') {
                continue;
            }

            $path = str_starts_with($line, 'a ') ? substr($line, 2) : $line;

            if (str_ends_with($path, '/') || is_dir(Path::join($projectDir, $path))) {
                continue;
            }

            ++$count;
        }

        return $count;
    }
}
