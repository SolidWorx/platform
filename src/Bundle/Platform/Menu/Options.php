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

namespace SolidWorx\Platform\PlatformBundle\Menu;

class Options
{
    /**
     * @var array{
     *     route?: string,
     *     routeParameters?: array<string, scalar>,
     *     routeAbsolute?: bool,
     * }
     */
    private array $options = [];

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    /***
     * @param array<string, scalar> $parameters
     */
    public function route(string $route, array $parameters = [], bool $absolute = false): self
    {
        $this->options['route'] = $route;
        $this->options['routeParameters'] = $parameters;
        $this->options['routeAbsolute'] = $absolute;
        return $this;
    }

    public function role(string $role): self
    {
        $this->options['extras'] ??= [];
        $this->options['extras']['role'] = $role;
        return $this;
    }

    public function icon(string $icon): self
    {
        $this->options['extras'] ??= [];
        $this->options['extras']['icon'] = $icon;
        return $this;
    }

    /**
     * @return array{
     *     route?: string,
     *     routeParameters?: array<string, scalar>,
     *     routeAbsolute?: bool,
     * }
     */
    public function build(): array
    {
        return $this->options;
    }
}
