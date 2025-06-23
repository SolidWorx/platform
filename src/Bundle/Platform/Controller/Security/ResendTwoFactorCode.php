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
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Email\Generator\CodeGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/2fa/resend', name: '_solidworx_platform_security_two_factor_resend')]
class ResendTwoFactorCode extends AbstractController
{
    public function __construct(
        private readonly CodeGeneratorInterface $codeGenerator
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $this->getUser();
        assert($user instanceof TwoFactorInterface);
        $this->codeGenerator->reSend($user);

        $this->addFlash('success', 'Two-factor authentication code has been re-sent.');

        return $this->redirectToRoute('2fa_login');
    }
}
