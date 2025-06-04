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
use SolidWorx\Platform\SaasBundle\Enum\LemonSqueezy\SubscriptionStatus;
use Symfony\Component\Serializer\Attribute\SerializedName;

class SubscriptionAttributes
{
    public int $storeId;

    public int $customerId;

    public int $orderId;

    public int $orderItemId;

    public int $productId;

    public int $variantId;

    public string $productName;

    public string $variantName;

    public string $userName;

    public string $userEmail;

    public SubscriptionStatus $status;

    public string $statusFormatted;

    public string $cardBrand;

    public string $cardLastFour;

    public ?string $pause = null;

    public bool $cancelled;

    #[SerializedName('trial_ends_at')]
    public ?DateTimeInterface $trialEndsAt;

    #[SerializedName('billing_anchor')]
    public int $billingAnchor;

    #[SerializedName('renews_at')]
    public ?DateTimeInterface $renewsAt;

    #[SerializedName('ends_at')]
    public ?DateTimeInterface $endsAt = null;

    #[SerializedName('created_at')]
    public DateTimeInterface $createdAt;

    #[SerializedName('updated_at')]
    public DateTimeInterface $updatedAt;

    #[SerializedName('test_mode')]
    public bool $testMode;

    public function __construct(
        #[SerializedName('first_subscription_item')]
        public FirstSubscriptionItem $firstSubscriptionItem = new FirstSubscriptionItem(),
        public SubscriptionUrls $urls = new SubscriptionUrls(),
    ) {
    }
}
