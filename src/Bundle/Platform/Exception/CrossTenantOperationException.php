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

namespace SolidWorx\Platform\PlatformBundle\Exception;

use RuntimeException;
use function sprintf;

/**
 * Thrown when a write is attempted against a tenant-aware entity that belongs to a different tenant
 * than the one currently in scope.
 */
final class CrossTenantOperationException extends RuntimeException
{
    public static function forEntity(string $entityClass): self
    {
        return new self(sprintf(
            'A cross-tenant write was blocked for entity "%s": the entity does not belong to the tenant currently in scope.',
            $entityClass,
        ));
    }
}
