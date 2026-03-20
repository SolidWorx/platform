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

/**
 * PHP fluent builder for `platform.yaml` configuration.
 *
 * Usage in platform.php:
 *
 *     return PlatformConfigBuilder::create()
 *         ->name('My App')
 *         ->security()
 *             ->twoFactor()
 *                 ->enabled(true)
 *                 ->baseTemplate('@App/2fa.html.twig')
 *             ->end()
 *         ->end()
 *         ->build();
 */
final class PlatformConfigBuilder
{
    private string $name = 'SolidWorx Platform';

    private string $version = '1.0.0';

    private ?SecurityConfigBuilder $security = null;

    private ?bool $enableUtcDate = null;

    /**
     * @var array<string, string>
     */
    private array $models = [];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $saas = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $ui = null;

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function version(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function security(): SecurityConfigBuilder
    {
        $this->security = SecurityConfigBuilder::create($this);
        return $this->security;
    }

    public function enableUtcDate(bool $enable = true): self
    {
        $this->enableUtcDate = $enable;
        return $this;
    }

    public function userModel(string $class): self
    {
        $this->models['user'] = $class;
        return $this;
    }

    /**
     * Allows embedding a raw saas config sub-array (e.g. from SaasConfigBuilder::build()).
     *
     * @param array<string, mixed> $saasConfig
     */
    public function withSaasConfig(array $saasConfig): self
    {
        $this->saas = $saasConfig;
        return $this;
    }

    /**
     * Allows embedding a raw ui config sub-array (e.g. from UiConfigBuilder::build()).
     *
     * @param array<string, mixed> $uiConfig
     */
    public function withUiConfig(array $uiConfig): self
    {
        $this->ui = $uiConfig;
        return $this;
    }

    /**
     * Returns the raw config array — no validation is performed at this stage.
     * Validation happens in each bundle's DI Extension.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $platform = [
            'name' => $this->name,
            'version' => $this->version,
        ];

        if ($this->security instanceof SecurityConfigBuilder) {
            $platform['security'] = $this->security->toArray();
        }

        if ($this->enableUtcDate !== null) {
            $platform['doctrine'] = [
                'types' => [
                    'enable_utc_date' => $this->enableUtcDate,
                ],
            ];
        }

        if ($this->models !== []) {
            $platform['models'] = $this->models;
        }

        if ($this->saas !== null) {
            $platform['saas'] = $this->saas;
        }

        if ($this->ui !== null) {
            $platform['ui'] = $this->ui;
        }

        return [
            'platform' => $platform,
        ];
    }
}
