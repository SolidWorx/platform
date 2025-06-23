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
}
