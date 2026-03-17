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

final class SecurityConfigBuilder
{
    private ?TwoFactorConfigBuilder $twoFactor = null;

    private function __construct(
        private readonly PlatformConfigBuilder $parent
    ) {
    }

    public static function create(PlatformConfigBuilder $parent): self
    {
        return new self($parent);
    }

    public function twoFactor(): TwoFactorConfigBuilder
    {
        $this->twoFactor = TwoFactorConfigBuilder::create($this);
        return $this->twoFactor;
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

        if ($this->twoFactor instanceof TwoFactorConfigBuilder) {
            $config['two_factor'] = $this->twoFactor->toArray();
        }

        return $config;
    }
}
