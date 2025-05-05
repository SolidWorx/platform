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

namespace SolidWorx\Platform\Bundle\Platform\Exception;

final class InvalidEntityException extends \InvalidArgumentException
{
    public function __construct(string $expected, string $actual, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct(sprintf('Entity must be an instance of "%s", "%s" given', $expected, $actual), $code, $previous);
    }
}
