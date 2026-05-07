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

namespace SolidWorx\Platform\SaasBundle\Exception;

use RuntimeException;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use Throwable;

class ActiveSubscriptionPlanChangeException extends RuntimeException
{
    public function __construct(Subscription $subscription, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Cannot change the plan on subscription "%s" while it is active.', $subscription->getId()->toBase58()),
            $code,
            $previous,
        );
    }
}
