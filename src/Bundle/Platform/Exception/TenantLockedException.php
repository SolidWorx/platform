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

/**
 * Thrown when code attempts to switch tenants while the tenant is locked to the request — for
 * example on a request arriving via a tenant's custom domain.
 */
final class TenantLockedException extends RuntimeException
{
}
