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

namespace SolidWorx\Platform\SaasBundle\Integration;

use SolidWorx\Platform\SaasBundle\Dto\IntegrationProduct;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;

interface PaymentIntegrationInterface
{
    /**
     * @return string The URL to redirect the user to for checkout
     */
    public function checkout(Subscription $subscription, array $additionalInfo = []): string;

    /**
     * @return iterable<IntegrationProduct>
     */
    public function getPlans(): iterable;

    public function getCustomerPortalUrl(Subscription $subscription): string;
}
