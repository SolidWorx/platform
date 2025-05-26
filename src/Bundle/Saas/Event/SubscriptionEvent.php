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

namespace SolidWorx\Platform\SaasBundle\Event;

use SolidWorx\Platform\SaasBundle\Dto\LemonSqueezy\Subscription;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\EventDispatcher\Event;

abstract class SubscriptionEvent extends Event
{
    public function __construct(
        public readonly Ulid $subscriptionId,
        public readonly string $externalId,
        /** @TODO: This should be a generic subscription DTO instead of integration-specific */
        public readonly ?Subscription $subscription = null,
    ) {
    }
}
