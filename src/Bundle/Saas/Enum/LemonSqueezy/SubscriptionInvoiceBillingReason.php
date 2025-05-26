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
 * @link https://docs.lemonsqueezy.com/api/subscription-invoices/the-subscription-invoice-object#billing_reason
 */
enum SubscriptionInvoiceBillingReason: string
{
    /**
     * The initial invoice generated when the subscription is created.
     */
    case INITIAL = 'initial';
    /**
     * A renewal invoice generated when the subscription is renewed.
     */
    case RENEWAL = 'renewal';
    /**
     * An invoice generated when the subscription is updated.
     */
    case UPDATED = 'updated';
}
