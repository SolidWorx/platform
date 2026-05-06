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

namespace SolidWorx\Platform\PlatformBundle\Feature;

final readonly class UpgradeOptions
{
    /**
     * @param list<PlanReference> $plans
     */
    public function __construct(
        public array $plans,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->plans === [];
    }
}
