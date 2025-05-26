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

namespace SolidWorx\Platform\SaasBundle\Dto\LemonSqueezy;

use DateTimeInterface;
use SolidWorx\Platform\SaasBundle\Enum\LemonSqueezy\SubscriptionInvoiceBillingReason;
use SolidWorx\Platform\SaasBundle\Enum\LemonSqueezy\SubscriptionInvoiceStatus;
use Symfony\Component\Serializer\Attribute\SerializedName;

class SubscriptionInvoiceAttributes
{
    #[SerializedName('store_id')]
    public int $storeId;

    #[SerializedName('subscription_id')]
    public int $subscriptionId;

    #[SerializedName('customer_id')]
    public int $customerId;

    #[SerializedName('user_name')]
    public string $userName;

    #[SerializedName('user_email')]
    public string $userEmail;

    #[SerializedName('billing_reason')]
    public SubscriptionInvoiceBillingReason $billingReason;

    #[SerializedName('card_brand')]
    public ?string $cardBrand = null;

    #[SerializedName('card_last_four')]
    public ?string $cardLastFour = null;

    public string $currency;

    #[SerializedName('currency_rate')]
    public string $currencyRate;

    public SubscriptionInvoiceStatus $status;

    #[SerializedName('status_formatted')]
    public string $statusFormatted;

    public bool $refunded;

    #[SerializedName('refunded_at')]
    public ?DateTimeInterface $refundedAt = null;

    public float $subtotal;

    #[SerializedName('discount_total')]
    public float $discountTotal;

    public float $tax;

    #[SerializedName('tax_inclusive')]
    public bool $taxInclusive;

    public float $total;

    #[SerializedName('refunded_amount')]
    public float $refundedAmount;

    #[SerializedName('subtotal_usd')]
    public float $subtotalUsd;

    #[SerializedName('discount_total_usd')]
    public float $discountTotalUsd;

    #[SerializedName('tax_usd')]
    public float $taxUsd;

    #[SerializedName('total_usd')]
    public float $totalUsd;

    #[SerializedName('refunded_amount_usd')]
    public float $refundedAmountUsd;

    #[SerializedName('subtotal_formatted')]
    public string $subtotalFormatted;

    #[SerializedName('discount_total_formatted')]
    public string $discountTotalFormatted;

    #[SerializedName('tax_formatted')]
    public string $taxFormatted;

    #[SerializedName('total_formatted')]
    public string $totalFormatted;

    #[SerializedName('refunded_amount_formatted')]
    public string $refundedAmountFormatted;

    #[SerializedName('created_at')]
    public DateTimeInterface $createdAt;

    #[SerializedName('updated_at')]
    public DateTimeInterface $updatedAt;

    #[SerializedName('test_mode')]
    public bool $testMode = false;

    public function __construct(
        public SubscriptionInvoiceUrls $urls = new SubscriptionInvoiceUrls(),
    ) {
    }
}
