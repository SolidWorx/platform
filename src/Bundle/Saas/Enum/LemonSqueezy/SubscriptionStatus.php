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
 * @link https://docs.lemonsqueezy.com/api/subscriptions/the-subscription-object#status
 */
enum SubscriptionStatus: string
{
    case ON_TRIAL = 'on_trial';

    case ACTIVE = 'active';

    /**
     * The subscription’s payment collection has been paused.
     */
    case PAUSED = 'paused';

    /**
     * A renewal payment has failed.
     * The subscription will go through 4 payment retries {@link https://docs.lemonsqueezy.com/help/online-store/recovery-dunning#failed-payments} over the course of 2 weeks.
     * If a retry is successful, the subscription’s status changes back to active.
     * If all four retries are unsuccessful, the status is changed to unpaid.
     */
    case PAST_DUE = 'past_due';

    /**
     * Payment recovery {@link https://docs.lemonsqueezy.com/help/online-store/recovery-dunning#failed-payments} has been unsuccessful in capturing a payment after 4 attempts.
     * If dunning {@link https://docs.lemonsqueezy.com/help/online-store/recovery-dunning#dunning} is enabled in your store,
     * your dunning rules now will determine if the subscription becomes expired after a certain period.
     * If dunning is turned off, the status remains unpaid (it is up to you to determine what this means for users of your product).
     */
    case UNPAID = 'unpaid';

    /**
     * The customer or store owner has canceled future payments,
     * but the subscription is still technically active and valid (on a “grace period”).
     * The ends_at value shows the date-time when the subscription is scheduled to expire.
     */
    case CANCELLED = 'cancelled';

    /**
     * The subscription has ended
     * (either it had previously been canceled and the grace period created from its final payment has run out,
     *  or it was previously unpaid and the subscription was not re-activated during dunning).
     * The ends_at value shows the date-time when the subscription expired.
     * Customers should no longer have access to your product.
     */
    case EXPIRED = 'expired';
}
