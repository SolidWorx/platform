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

namespace SolidWorx\Platform\PlatformBundle\Controller\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The page where a signed-in user turns their second factors on and off.
 *
 * It only mounts the {@see \SolidWorx\Platform\PlatformBundle\Twig\Components\Security\TwoFactor}
 * live component, which owns all of the behaviour. Both this controller and the component are
 * removed from the container when `platform.security.two_factor.enabled` is false.
 *
 * Deliberately *not* under `/2fa`: that prefix is covered by the
 * `IS_AUTHENTICATED_2FA_IN_PROGRESS` access-control rules, which only match users who are
 * half-way through the login challenge — the exact opposite of who this page is for.
 */
#[Route(path: self::PATH, name: self::ROUTE_NAME, methods: ['GET'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class TwoFactorConfiguration extends AbstractController
{
    /**
     * The path of the two-factor configuration page.
     */
    public const string PATH = '/settings/two-factor';

    /**
     * The route name of the two-factor configuration page, linked to from the user menu.
     */
    public const string ROUTE_NAME = 'solidworx_platform_security_two_factor_configure';

    public function __invoke(): Response
    {
        return $this->render('@SolidWorxPlatform/Security/TwoFactor/configure.html.twig');
    }
}
