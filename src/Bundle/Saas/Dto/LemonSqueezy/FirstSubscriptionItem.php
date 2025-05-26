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
use Symfony\Component\Serializer\Attribute\SerializedName;

class FirstSubscriptionItem
{
    public int $id;

    #[SerializedName('subscription_id')]
    public int $subscriptionId;

    #[SerializedName('price_id')]
    public int $priceId;

    public int $quantity;

    #[SerializedName('is_usage_based')]
    public bool $isUsageBased;

    #[SerializedName('created_at')]
    public DateTimeInterface $createdAt;

    #[SerializedName('updated_at')]
    public DateTimeInterface $updatedAt;
}
