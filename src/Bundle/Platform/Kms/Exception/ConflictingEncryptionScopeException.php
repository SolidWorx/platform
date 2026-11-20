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

namespace SolidWorx\Platform\PlatformBundle\Kms\Exception;

use LogicException;
use function sprintf;

/**
 * An encryption scope is used by a tenant-aware column and by a column that is not, so it can be
 * keyed neither per tenant nor shared without breaking one of them.
 */
final class ConflictingEncryptionScopeException extends LogicException
{
    public function __construct(string $scope, string $tenantAwareClass, string $sharedClass)
    {
        parent::__construct(sprintf(
            'The encryption scope "%s" is used by the tenant-aware "%s" and by "%s", which is not tenant-aware. Give the columns different keys, or make both classes tenant-aware.',
            $scope,
            $tenantAwareClass,
            $sharedClass,
        ));
    }
}
