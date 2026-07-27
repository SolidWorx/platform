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
use function sprintf;

class NoFreePlanConfiguredException extends RuntimeException
{
    public function __construct(?Subscription $subscription = null, int $code = 0, ?Throwable $previous = null)
    {
        $message = $subscription instanceof Subscription
            ? sprintf('Cannot downgrade subscription "%s": no active free plan is configured.', $subscription->getId()->toBase58())
            : 'Cannot downgrade to the free plan: no active free plan is configured.';

        parent::__construct($message, $code, $previous);
    }
}
