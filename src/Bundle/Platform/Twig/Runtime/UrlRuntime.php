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

namespace SolidWorx\Platform\PlatformBundle\Twig\Runtime;

use Twig\Environment;

final class UrlRuntime
{
    public function logoutLink(Environment $twig, ?string $firewall = null): string
    {
        return $twig->render('@SolidWorxPlatform/Components/logout_link.html.twig', [
            'firewall' => $firewall,
        ]);
    }
}
