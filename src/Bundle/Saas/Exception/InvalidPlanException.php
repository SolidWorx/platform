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
use Throwable;

class InvalidPlanException extends RuntimeException
{
    public function __construct(string $plan, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('The plan "%s" is invalid.', $plan), $code, $previous);
    }
}
