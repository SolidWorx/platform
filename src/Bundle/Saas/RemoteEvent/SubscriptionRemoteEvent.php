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

namespace SolidWorx\Platform\SaasBundle\RemoteEvent;

use SolidWorx\Platform\SaasBundle\Dto\LemonSqueezy\Subscription;
use SolidWorx\Platform\SaasBundle\Enum\LemonSqueezy\Event;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Uid\Ulid;

final class SubscriptionRemoteEvent extends RemoteEvent
{
    public function __construct(
        public readonly Ulid $subscriptionId,
        public readonly Subscription $subscription,
        public readonly Event $event,
        array $payload = [],
    ) {
        parent::__construct(
            $event->value,
            $subscriptionId->toBase58(),
            $payload,
        );
    }
}
