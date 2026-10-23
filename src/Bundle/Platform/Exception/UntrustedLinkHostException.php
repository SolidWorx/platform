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

use LogicException;

/**
 * Thrown instead of emailing a link built on a request host that nothing restricts — see
 * {@see \SolidWorx\Platform\PlatformBundle\Security\EmailLinkContext}.
 */
final class UntrustedLinkHostException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Emailed links are built from the request host, which nothing restricts. Set "framework.router.default_uri" or "platform.security.base_url" to the URL of your application, or restrict the host with "framework.trusted_hosts".');
    }
}
