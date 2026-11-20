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

use RuntimeException;
use function sprintf;

/**
 * A column of a tenant-aware entity was encrypted or decrypted while no tenant was active.
 */
final class MissingTenantContextException extends RuntimeException
{
    public function __construct(string $scope)
    {
        parent::__construct(sprintf(
            'No tenant is active for the encryption scope "%s". Encrypted tenant data can only be accessed inside a tenant context.',
            $scope,
        ));
    }
}
