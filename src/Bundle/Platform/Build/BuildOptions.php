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

use Webmozart\Assert\Assert;
use function array_diff;
use function array_unique;
use function array_values;
use function in_array;
use function is_string;
use function sprintf;

/**
 * Everything a single invocation of `platform:build` needs, resolved once.
 *
 * @phpstan-type BuildConfig array{
 *   name: string,
 *   description: string,
 *   binary_name: string,
 *   env_prefix: string,
 *   default_port: string,
 *   output_dir: string,
 *   work_dir: string,
 *   php: array{version: string|null, extensions: list<string>, add: list<string>, remove: list<string>, extension_libs: list<string>},
 *   exclude: list<string>,
 *   hooks: array{install_check: string|null, on_boot: list<string>}
 * }
 */
final readonly class BuildOptions
{
    /**
     * @param list<string> $explicitExtensions
     * @param list<string> $addExtensions
     * @param list<string> $removeExtensions
     * @param list<string> $phpExtensionLibs
     * @param list<string> $exclude
     * @param list<string> $bootCommands
     */
    private function __construct(
        public string $appName,
        public string $description,
        public string $binaryName,
        public string $envPrefix,
        public string $defaultPort,
        public string $version,
        public string $outputDir,
        public string $workDir,
        public ?string $phpVersion,
        private array $explicitExtensions,
        private array $addExtensions,
        private array $removeExtensions,
        public array $phpExtensionLibs,
        public array $exclude,
        public ?string $installCheckCommand,
        public array $bootCommands,
    ) {
    }

    /**
     * @param array<string, mixed>                                        $config    The `solidworx_platform.build` parameter.
     * @param array{output_dir?: string|null, php_version?: string|null} $overrides Command-line overrides; null values are ignored.
     */
    public static function fromConfig(array $config, string $version, array $overrides): self
    {
        /** @var BuildConfig $config */
        $outputDir = $overrides['output_dir'] ?? null;
        $phpVersion = $overrides['php_version'] ?? null;

        return new self(
            appName: $config['name'],
            description: $config['description'],
            binaryName: $config['binary_name'],
            envPrefix: $config['env_prefix'],
            defaultPort: $config['default_port'],
            version: $version,
            outputDir: is_string($outputDir) ? $outputDir : $config['output_dir'],
            workDir: $config['work_dir'],
            phpVersion: is_string($phpVersion) ? $phpVersion : $config['php']['version'],
            explicitExtensions: $config['php']['extensions'],
            addExtensions: $config['php']['add'],
            removeExtensions: $config['php']['remove'],
            phpExtensionLibs: $config['php']['extension_libs'],
            exclude: $config['exclude'],
            installCheckCommand: $config['hooks']['install_check'],
            bootCommands: $config['hooks']['on_boot'],
        );
    }

    /**
     * The extension set to compile, or an empty list meaning "let build-static.sh derive it from
     * composer.json".
     *
     * Deriving from composer.json happens inside build-static.sh, which only does so when
     * PHP_EXTENSIONS is unset — there is no hook to post-process that set. So the moment an
     * application adds or removes anything, the base switches to an explicit list: its own if it
     * configured one, otherwise the defaults shipped in build-static.sh.
     *
     * @param list<string> $defaultExtensions The `defaultExtensions` list parsed out of build-static.sh.
     *
     * @return list<string>
     */
    public function resolveExtensions(array $defaultExtensions): array
    {
        if ($this->explicitExtensions === [] && $this->addExtensions === [] && $this->removeExtensions === []) {
            return [];
        }

        $base = $this->explicitExtensions === [] ? $defaultExtensions : $this->explicitExtensions;

        $resolved = array_values(array_diff($base, $this->removeExtensions));

        foreach ($this->addExtensions as $extension) {
            if (! in_array($extension, $resolved, strict: true)) {
                $resolved[] = $extension;
            }
        }

        return array_values(array_unique($resolved));
    }

    public function binaryFileName(string $os, string $arch): string
    {
        Assert::stringNotEmpty($os);
        Assert::stringNotEmpty($arch);

        return sprintf('%s-%s-%s', $this->binaryName, $os, $arch);
    }
}
