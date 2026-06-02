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

use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Email\Generator\CodeGenerator;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Email\Generator\CodeGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: self::PATH, name: self::ROUTE_NAME)]
class ResendTwoFactorCode extends AbstractController
{
    /**
     * The path of the resend-code endpoint.
     *
     * This is the single source of truth for the route: it is referenced both by the
     * route attribute above and by the 2FA access-control rule in
     * {@see \SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension\TwoFactorExtension::securityConfig()},
     * so the two can never drift.
     */
    public const string PATH = '/2fa/resend';

    /**
     * The route name of the resend-code endpoint (used by the 2FA Twig templates).
     */
    public const string ROUTE_NAME = '_solidworx_platform_security_two_factor_resend';

    public function __construct(
        private readonly CodeGeneratorInterface $codeGenerator
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $this->getUser();
        assert($user instanceof TwoFactorInterface);
        assert($this->codeGenerator instanceof CodeGenerator);
        $this->codeGenerator->reSend($user);

        $this->addFlash('success', 'Two-factor authentication code has been re-sent.');

        return $this->redirectToRoute('2fa_login');
    }
}
