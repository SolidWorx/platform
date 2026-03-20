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

namespace SolidWorx\Platform\SaasBundle\Config\Builder;

final class SaasPaymentConfigBuilder
{
    private string $returnRoute = '';

    private function __construct(
        private readonly SaasConfigBuilder $parent
    ) {
    }

    public static function create(SaasConfigBuilder $parent): self
    {
        return new self($parent);
    }

    public function returnRoute(string $route): self
    {
        $this->returnRoute = $route;
        return $this;
    }

    public function end(): SaasConfigBuilder
    {
        return $this->parent;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'return_route' => $this->returnRoute,
        ];
    }
}
