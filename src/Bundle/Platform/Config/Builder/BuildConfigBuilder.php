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

namespace SolidWorx\Platform\PlatformBundle\Config\Builder;

use function array_values;

/**
 * PHP fluent builder for the `platform.build:` configuration section (`platform:build`).
 *
 * Usage:
 *
 *     return PlatformConfigBuilder::create()
 *         ->name('Acme')
 *         ->binaryBuild()
 *             ->binaryName('acme')
 *             ->envPrefix('ACME')
 *         ->end()
 *         ->build();
 */
final class BuildConfigBuilder
{
    private ?string $name = null;

    private ?string $description = null;

    private ?string $binaryName = null;

    private ?string $envPrefix = null;

    private ?string $defaultPort = null;

    private ?string $outputDir = null;

    private ?string $workDir = null;

    private ?string $phpVersion = null;

    /**
     * @var list<string>
     */
    private array $extensions = [];

    /**
     * @var list<string>
     */
    private array $add = [];

    /**
     * @var list<string>
     */
    private array $remove = [];

    /**
     * @var list<string>
     */
    private array $exclude = [];

    private ?string $installCheck = null;

    /**
     * @var list<string>
     */
    private array $onBoot = [];

    private function __construct(
        private readonly PlatformConfigBuilder $parent
    ) {
    }

    public static function create(PlatformConfigBuilder $parent): self
    {
        return new self($parent);
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function binaryName(string $binaryName): self
    {
        $this->binaryName = $binaryName;
        return $this;
    }

    public function envPrefix(string $envPrefix): self
    {
        $this->envPrefix = $envPrefix;
        return $this;
    }

    public function defaultPort(string $port): self
    {
        $this->defaultPort = $port;
        return $this;
    }

    public function outputDir(string $dir): self
    {
        $this->outputDir = $dir;
        return $this;
    }

    public function workDir(string $dir): self
    {
        $this->workDir = $dir;
        return $this;
    }

    public function phpVersion(string $version): self
    {
        $this->phpVersion = $version;
        return $this;
    }

    public function extensions(string ...$extensions): self
    {
        $this->extensions = array_values($extensions);
        return $this;
    }

    public function addExtensions(string ...$extensions): self
    {
        $this->add = array_values($extensions);
        return $this;
    }

    public function removeExtensions(string ...$extensions): self
    {
        $this->remove = array_values($extensions);
        return $this;
    }

    public function exclude(string ...$paths): self
    {
        $this->exclude = array_values($paths);
        return $this;
    }

    public function installCheck(string $command): self
    {
        $this->installCheck = $command;
        return $this;
    }

    public function onBoot(string ...$commands): self
    {
        $this->onBoot = array_values($commands);
        return $this;
    }

    public function end(): PlatformConfigBuilder
    {
        return $this->parent;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $config = [];

        foreach ([
            'name' => $this->name,
            'description' => $this->description,
            'binary_name' => $this->binaryName,
            'env_prefix' => $this->envPrefix,
            'default_port' => $this->defaultPort,
            'output_dir' => $this->outputDir,
            'work_dir' => $this->workDir,
        ] as $key => $value) {
            if ($value !== null) {
                $config[$key] = $value;
            }
        }

        $php = [];

        if ($this->phpVersion !== null) {
            $php['version'] = $this->phpVersion;
        }

        foreach ([
            'extensions' => $this->extensions,
            'add' => $this->add,
            'remove' => $this->remove,
        ] as $key => $value) {
            if ($value !== []) {
                $php[$key] = $value;
            }
        }

        if ($php !== []) {
            $config['php'] = $php;
        }

        if ($this->exclude !== []) {
            $config['exclude'] = $this->exclude;
        }

        $hooks = [];

        if ($this->installCheck !== null) {
            $hooks['install_check'] = $this->installCheck;
        }

        if ($this->onBoot !== []) {
            $hooks['on_boot'] = $this->onBoot;
        }

        if ($hooks !== []) {
            $config['hooks'] = $hooks;
        }

        return $config;
    }
}
