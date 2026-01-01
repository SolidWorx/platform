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

namespace SolidWorx\Platform\PlatformBundle\Config;

use function array_key_exists;
use function explode;

final readonly class PlatformConfig
{

    private string $name;
    private string $version;
    private array $models;
    private array $templates;

    public static function create(): self
    {
        return new self([]);
    }

    public function withName(string $name): self
    {
        $config = $this->config;
        $config['name'] = $name;

        return new self($config);
    }

    public function withVersion(string $version): self
    {
        $config = $this->config;
        $config['version'] = $version;

        return new self($config);
    }

    public function withModel(string $name, string $value): self
    {
        $config = $this->config;
        $config['models'][$name] = $value;

        return new self($config);
    }

    public function withTemplate(string $name, string $value): self
    {
        $config = $this->config;
        $config['templates'][$name] = $value;

        return new self($config);
    }

    public function registerBundle(string $bundleClass, ?callable $bundleConfig = null): self
    {
        $config = $this->config;
        $bundles = $config['bundles'] ?? [];
        $bundles[$bundleClass] = $bundleConfig;
        $config['bundles'] = $bundles;

        return new self($config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $config
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $config = $this->config;

        foreach (explode('.', $key) as $name) {
            if (! array_key_exists($name, $config)) {
                return $default;
            }

            $config = $config[$name];
        }

        return $config;
    }

    /**
     * @return array<string, scalar>
     */
    public function toArray(): array
    {
        return $this->config;
    }
}
