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

use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Thrown when an authenticated user attempts to enter a tenant they are not a member of.
 *
 * Extends Symfony's {@see AccessDeniedException} so the security layer turns it into a 403 response
 * in an HTTP context.
 */
final class TenantAccessDeniedException extends AccessDeniedException
{
}
