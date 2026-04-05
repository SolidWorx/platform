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
use SolidWorx\Platform\SaasBundle\Trial\TrialUserInterface;
use Throwable;

final class TrialAlreadyExistsException extends RuntimeException
{
    public function __construct(TrialUserInterface $user, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf('A trial already exists for user "%s".', $user->getId()->toBase58()),
            $code,
            $previous,
        );
    }
}
