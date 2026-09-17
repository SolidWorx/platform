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

use const JSON_THROW_ON_ERROR;
use JsonException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use function array_keys;
use function file_get_contents;
use function is_array;
use function is_file;
use function json_decode;
use function sprintf;
use function strtok;
use function trim;

/**
 * Everything that must be true before a build is worth starting.
 *
 * Failures are collected rather than thrown one at a time: a developer missing three tools should
 * learn that in one run, not across three failed builds.
 */
final readonly class Preflight
{
    /**
     * Tool name to the hint shown when it is missing. Deeper build dependencies (autoconf, bison,
     * cmake and friends) are deliberately absent — static-php-cli's own `doctor --auto-fix` installs
     * those, and duplicating its list here would rot.
     *
     * @var array<string, string>
     */
    private const array REQUIRED_TOOLS = [
        'git' => 'macOS: brew install git · Debian/Ubuntu: apt-get install git · Fedora: dnf install git',
        'go' => 'macOS: brew install go · Debian/Ubuntu: apt-get install golang · Fedora: dnf install golang',
        'composer' => 'macOS: brew install composer · other: https://getcomposer.org/download/',
        'curl' => 'macOS: preinstalled · Debian/Ubuntu: apt-get install curl · Fedora: dnf install curl',
        'jq' => 'macOS: brew install jq · Debian/Ubuntu: apt-get install jq · Fedora: dnf install jq',
        'tar' => 'macOS: preinstalled · Debian/Ubuntu: apt-get install tar · Fedora: dnf install tar',
        'bash' => 'macOS: preinstalled · Debian/Ubuntu: apt-get install bash · Fedora: dnf install bash',
    ];

    public function __construct(
        private ExecutableFinder $executableFinder,
    ) {
    }

    /**
     * @return list<Problem>
     */
    public function run(string $projectDir, bool $skipAppChecks): array
    {
        $problems = $this->checkTools();

        if (! $skipAppChecks) {
            return [...$problems, ...$this->checkApplication($projectDir)];
        }

        return $problems;
    }

    /**
     * Versions of the tools that were found, for the success line. Tools that are missing or refuse
     * to report a version are simply absent.
     *
     * These processes run `<tool> --version`, which every tool here (including git) answers without
     * touching any repository state — so, unlike VersionResolver::run(), there is nothing to
     * sanitise out of the inherited environment.
     *
     * @return array<string, string>
     */
    public function toolVersions(): array
    {
        $versions = [];

        foreach (array_keys(self::REQUIRED_TOOLS) as $tool) {
            $path = $this->executableFinder->find($tool);

            if ($path === null) {
                continue;
            }

            $process = new Process([$path, '--version']);
            $process->run();

            if (! $process->isSuccessful()) {
                continue;
            }

            $firstLine = strtok(trim($process->getOutput()), "\n");

            if ($firstLine !== false) {
                $versions[$tool] = $firstLine;
            }
        }

        return $versions;
    }

    /**
     * @return list<Problem>
     */
    private function checkTools(): array
    {
        $problems = [];

        foreach (self::REQUIRED_TOOLS as $tool => $hint) {
            if ($this->executableFinder->find($tool) === null) {
                $problems[] = Problem::error(sprintf('%s is not installed', $tool), $hint);
            }
        }

        return $problems;
    }

    /**
     * @return list<Problem>
     */
    private function checkApplication(string $projectDir): array
    {
        $problems = [];

        if (! is_file($projectDir . '/vendor/autoload_runtime.php')) {
            $problems[] = Problem::error(
                'Dependencies are not installed',
                'Run: composer install --no-dev --optimize-autoloader',
            );
        }

        if (! is_file($projectDir . '/public/build/manifest.json')) {
            $problems[] = Problem::error(
                'Frontend assets are not built',
                'Run the asset build (for example: bun run build) before building the binary.',
            );
        }

        if ($this->hasDevDependencies($projectDir)) {
            $problems[] = Problem::warning(
                'Development dependencies will be embedded',
                'The archive is the checkout as it stands. Run composer install --no-dev --optimize-autoloader for a lean binary.',
            );
        }

        return $problems;
    }

    private function hasDevDependencies(string $projectDir): bool
    {
        $installed = $projectDir . '/vendor/composer/installed.json';

        if (! is_file($installed)) {
            return false;
        }

        $contents = file_get_contents($installed);

        if ($contents === false) {
            return false;
        }

        try {
            $data = json_decode($contents, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return is_array($data) && ($data['dev'] ?? false) === true;
    }
}
