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

namespace SolidWorx\Platform\SaasBundle\Message\LemonSqueezy;

use SolidWorx\Platform\SaasBundle\Dto\LemonSqueezy\Subscription;
use SolidWorx\Platform\SaasBundle\Enum\LemonSqueezy\Event;
use Symfony\Component\RemoteEvent\RemoteEvent;

class SubscriptionCreatedMessage extends RemoteEvent
{
    public function __construct(string $id, Subscription $payload)
    {
        parent::__construct(Event::SUBSCRIPTION_CREATED->value, $id, (array) $payload);
    }
}
