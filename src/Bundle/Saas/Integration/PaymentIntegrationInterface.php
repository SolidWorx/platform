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

use DateTimeImmutable;
use SolidWorx\Platform\SaasBundle\Dto\IntegrationProduct;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;

interface PaymentIntegrationInterface
{
    /**
     * @return string The URL to redirect the user to for checkout
     */
    public function checkout(Subscription $subscription, ?Options $options = null): string;

    /**
     * @return iterable<IntegrationProduct>
     */
    public function getPlans(): iterable;

    public function getCustomerPortalUrl(Subscription $subscription): string;

    /**
     * Switch the plan on an active subscription via the payment provider.
     * Returns the new period end (renew) date as reported by the provider.
     */
    public function changePlan(Subscription $subscription, Plan $newPlan): DateTimeImmutable;

    /**
     * Cancel the subscription at the end of the current paid period. The
     * subscription remains usable until the period ends.
     */
    public function cancelAtPeriodEnd(Subscription $subscription): DateTimeImmutable;

    /**
     * Reverse a pending cancellation. Returns the renew date now restored by
     * the provider.
     */
    public function resume(Subscription $subscription): DateTimeImmutable;
}
