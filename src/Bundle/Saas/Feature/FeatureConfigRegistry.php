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

namespace SolidWorx\Platform\SaasBundle\Feature;

use SolidWorx\Platform\SaasBundle\Enum\FeatureType;
use SolidWorx\Platform\SaasBundle\Exception\UndefinedFeatureException;

final class FeatureConfigRegistry
{
    /**
     * @var array<string, FeatureConfig>
     */
    private array $features = [];

    /**
     * @param array<string, array{type: string, default: int|bool|string|array<mixed>, description?: string}> $featureConfigs
     */
    public function __construct(array $featureConfigs = [])
    {
        foreach ($featureConfigs as $key => $config) {
            $this->features[$key] = new FeatureConfig(
                $key,
                FeatureType::from($config['type']),
                $config['default'],
                $config['description'] ?? '',
            );
        }
    }

    public function has(string $key): bool
    {
        return isset($this->features[$key]);
    }

    public function get(string $key): FeatureConfig
    {
        if (! $this->has($key)) {
            throw new UndefinedFeatureException($key);
        }

        return $this->features[$key];
    }

    /**
     * @return array<string, FeatureConfig>
     */
    public function all(): array
    {
        return $this->features;
    }

    /**
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->features);
    }
}
