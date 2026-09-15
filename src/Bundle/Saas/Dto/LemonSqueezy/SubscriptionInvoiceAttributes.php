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
    #[SerializedName(serializedName: 'store_id')]
    public int $storeId;

    #[SerializedName(serializedName: 'subscription_id')]
    public int $subscriptionId;

    #[SerializedName(serializedName: 'customer_id')]
    public int $customerId;

    #[SerializedName(serializedName: 'user_name')]
    public string $userName;

    #[SerializedName(serializedName: 'user_email')]
    public string $userEmail;

    #[SerializedName(serializedName: 'billing_reason')]
    public SubscriptionInvoiceBillingReason $billingReason;

    #[SerializedName(serializedName: 'card_brand')]
    public ?string $cardBrand = null;

    #[SerializedName(serializedName: 'card_last_four')]
    public ?string $cardLastFour = null;

    public string $currency;

    #[SerializedName(serializedName: 'currency_rate')]
    public string $currencyRate;

    public SubscriptionInvoiceStatus $status;

    #[SerializedName(serializedName: 'status_formatted')]
    public string $statusFormatted;

    public bool $refunded;

    #[SerializedName(serializedName: 'refunded_at')]
    public ?DateTimeInterface $refundedAt = null;

    public float $subtotal;

    #[SerializedName(serializedName: 'discount_total')]
    public float $discountTotal;

    public float $tax;

    #[SerializedName(serializedName: 'tax_inclusive')]
    public bool $taxInclusive;

    public float $total;

    #[SerializedName(serializedName: 'refunded_amount')]
    public float $refundedAmount;

    #[SerializedName(serializedName: 'subtotal_usd')]
    public float $subtotalUsd;

    #[SerializedName(serializedName: 'discount_total_usd')]
    public float $discountTotalUsd;

    #[SerializedName(serializedName: 'tax_usd')]
    public float $taxUsd;

    #[SerializedName(serializedName: 'total_usd')]
    public float $totalUsd;

    #[SerializedName(serializedName: 'refunded_amount_usd')]
    public float $refundedAmountUsd;

    #[SerializedName(serializedName: 'subtotal_formatted')]
    public string $subtotalFormatted;

    #[SerializedName(serializedName: 'discount_total_formatted')]
    public string $discountTotalFormatted;

    #[SerializedName(serializedName: 'tax_formatted')]
    public string $taxFormatted;

    #[SerializedName(serializedName: 'total_formatted')]
    public string $totalFormatted;

    #[SerializedName(serializedName: 'refunded_amount_formatted')]
    public string $refundedAmountFormatted;

    #[SerializedName(serializedName: 'created_at')]
    public DateTimeInterface $createdAt;

    #[SerializedName(serializedName: 'updated_at')]
    public DateTimeInterface $updatedAt;

    #[SerializedName(serializedName: 'test_mode')]
    public bool $testMode = false;

    public function __construct(
        public SubscriptionInvoiceUrls $urls = new SubscriptionInvoiceUrls(),
    ) {
    }
}
