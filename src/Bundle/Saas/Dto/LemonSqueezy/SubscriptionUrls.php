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

use Symfony\Component\Serializer\Attribute\SerializedName;

class SubscriptionUrls
{
    #[SerializedName('update_payment_method')]
    public string $updatePaymentMethod;

    #[SerializedName('customer_portal')]
    public string $customerPortal;

    #[SerializedName('customer_portal_update_subscription')]
    public string $customerPortalUpdateSubscription;
}
