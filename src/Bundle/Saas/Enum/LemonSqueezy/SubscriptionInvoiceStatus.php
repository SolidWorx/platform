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
 * @link https://docs.lemonsqueezy.com/api/subscription-invoices/the-subscription-invoice-object#status
 */
enum SubscriptionInvoiceStatus: string
{
    /**
     *The invoice is waiting for payment
     */
    case PENDING = 'pending';
    /**
     * The invoice has been paid
     */
    case PAID = 'paid';
    /**
     * The invoice was cancelled or cannot be paid
     */
    case VOID = 'void';
    /**
     * The invoice was paid but has since been fully refunded
     */
    case REFUNDED = 'refunded';
    /**
     * The invoice was paid but has since been partially refunded
     */
    case PARTIAL_REFUND = 'partial_refund';
}
