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

namespace SolidWorx\Platform\SaasBundle\Enum\LemonSqueezy;

/**
 * @link https://docs.lemonsqueezy.com/help/webhooks/event-types
 */
enum Event: string
{
    /**
     * Occurs when a new subscription is successfully created.
     * An order_created event will always be sent alongside a subscription_created event.
     */
    case SUBSCRIPTION_CREATED = 'subscription_created';

    /**
     * Occurs when a subscription’s data is changed or updated.
     * This event can be used as a “catch-all” to make sure you always have access to the latest subscription data.
     */
    case SUBSCRIPTION_UPDATED = 'subscription_updated';

    /**
     * Occurs when a subscription payment is successful.
     */
    case SUBSCRIPTION_PAYMENT_SUCCESS = 'subscription_payment_success';

    /**
     * Occurs when a subscription renewal payment fails.
     */
    case SUBSCRIPTION_PAYMENT_FAILED = 'subscription_payment_failed';

    /**
     * Occurs when a subscription has a successful payment after a failed payment.
     * A subscription_payment_success event will always be sent alongside a subscription_payment_recovered event.
     */
    case SUBSCRIPTION_PAYMENT_RECOVERED = 'subscription_payment_recovered';

    /**
     * Occurs when a subscription payment is refunded.
     */
    case SUBSCRIPTION_PAYMENT_REFUNDED = 'subscription_payment_refunded';

    /**
     * Occurs when a subscription is cancelled manually by the customer or store owner.
     * The subscription enters a “grace period” until the next billing date, when it will expire.
     * It is possible for the subscription to be resumed during this period.
     */
    case SUBSCRIPTION_CANCELLED = 'subscription_cancelled';

    /**
     * Occurs when a subscription’s payment collection is paused.
     */
    case SUBSCRIPTION_PAUSED = 'subscription_paused';

    /**
     * Occurs when a subscription’s payment collection is resumed after being previously paused.
     */
    case SUBSCRIPTION_UNPAUSED = 'subscription_unpaused';

    /**
     * Occurs when a subscription is resumed after being previously cancelled.
     */
    case SUBSCRIPTION_RESUMED = 'subscription_resumed';

    /**
     * Occurs when a subscription has ended after being previously cancelled,
     * or once dunning has been completed for past_due subscriptions.
     * You can manage how long to wait before the system marks delinquent subscriptions as expired. {@link https://docs.lemonsqueezy.com/help/online-store/recovery-dunning}
     */
    case SUBSCRIPTION_EXPIRED = 'subscription_expired';
}
