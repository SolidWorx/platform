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

namespace SolidWorx\Platform\SaasBundle\Subscription;

use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;

/**
 * Interface for retrieving subscriptions.
 *
 * This interface allows for dependency injection of subscription lookup
 * without coupling to the full SubscriptionManager implementation.
 */
interface SubscriptionProviderInterface
{
    public function getSubscriptionFor(SubscribableInterface $subscriber): ?Subscription;
}
