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

namespace SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword;

use SolidWorx\Platform\PlatformBundle\Attributes\WithoutTenant;
use SolidWorx\Platform\PlatformBundle\Controller\BaseController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * "Check your email" — shown after every request, whether or not a link was actually sent.
 */
#[WithoutTenant]
final class CheckResetPasswordEmail extends BaseController
{
    public const string PATH = '/reset-password/check-email';

    public const string ROUTE_NAME = '_solidworx_platform_reset_password_check_email';

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        #[Autowire(param: 'solidworx_platform.security.reset_password.templates.check_email')]
        private readonly string $template,
        #[Autowire(param: 'solidworx_platform.security.reset_password.firewall')]
        private readonly string $firewall,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $token = $request->getSession()->get(RequestResetPassword::SESSION_TOKEN);

        return $this->render($this->template, [
            // A fake one when the page is opened directly, so it reads the same either way.
            'reset_token' => $token instanceof ResetPasswordToken ? $token : $this->resetPasswordHelper->generateFakeResetToken(),
            'login_route' => '_login_' . $this->firewall,
        ]);
    }
}
