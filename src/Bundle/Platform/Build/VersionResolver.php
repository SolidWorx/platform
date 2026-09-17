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

use Carbon\CarbonImmutable;
use Symfony\Component\Process\Process;
use function trim;

/**
 * Decides the version stamped into a built binary.
 *
 * The checkout itself is the source of truth: an exact tag on HEAD wins, then the branch name,
 * then the short commit hash. Outside a git checkout the build still gets a unique, sortable
 * identifier rather than failing.
 */
final class VersionResolver
{
    public function resolve(string $projectDir): string
    {
        foreach ([
            ['describe', '--tags', '--exact-match'],
            ['rev-parse', '--abbrev-ref', 'HEAD'],
            ['rev-parse', '--short', 'HEAD'],
        ] as $command) {
            $version = $this->run($command, $projectDir);

            // A detached HEAD reports the literal string "HEAD" as its branch; that is not a version.
            if ($version !== null && $version !== 'HEAD') {
                return $version;
            }
        }

        return 'dev-' . CarbonImmutable::now()->format('YmdHis');
    }

    /**
     * @param list<string> $command
     */
    private function run(array $command, string $projectDir): ?string
    {
        // A parent process that is itself a git hook (e.g. this repo's own pre-commit hook, which
        // runs the test suite) exports these into the environment. Left inherited, they would
        // redirect git commands run here at that unrelated repository instead of $projectDir.
        $process = new Process(['git', ...$command], $projectDir, [
            'GIT_DIR' => false,
            'GIT_WORK_TREE' => false,
            'GIT_INDEX_FILE' => false,
            'GIT_PREFIX' => false,
            'GIT_COMMON_DIR' => false,
        ]);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $output = trim($process->getOutput());

        return $output === '' ? null : $output;
    }
}
