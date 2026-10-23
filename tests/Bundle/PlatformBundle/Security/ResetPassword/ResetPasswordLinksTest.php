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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Security\ResetPassword;

use PHPUnit\Framework\Attributes\CoversNothing;
use SolidWorx\Platform\PlatformBundle\Form\Type\Profile\ChangePasswordType;
use SolidWorx\Platform\PlatformBundle\Security\Password\PasswordPolicyInterface;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Controller\Security\ResetPassword\ResetPasswordTestCase;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Profile\ProfileTestKernel;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;

/**
 * The places a user who forgot their password looks for the way out: the login page, the
 * change-password page — and the email itself.
 */
#[CoversNothing]
final class ResetPasswordLinksTest extends ResetPasswordTestCase
{
    public function testTheLoginPageLinksToTheResetForm(): void
    {
        $this->push(Request::create('/login'));

        $html = $this->twig()->render('@Ui/Security/login.html.twig', [
            'last_username' => '',
            'error' => null,
            'options' => [
                'check_path' => '/login_check',
                'username_parameter' => '_username',
                'password_parameter' => '_password',
                'csrf_parameter' => '_csrf_token',
                'csrf_token_id' => 'authenticate',
                'enable_csrf' => false,
                'remember_me_parameter' => null,
                'always_remember_me' => false,
            ],
        ]);

        self::assertStringContainsString('<a href="/reset-password">Forgot password?</a>', $html);
    }

    public function testTheChangePasswordPageLinksToTheResetForm(): void
    {
        $this->push(Request::create('/profile/password'));
        $this->service('security.token_storage', TokenStorageInterface::class)
            ->setToken(new UsernamePasswordToken($this->user, 'main', $this->user->getRoles()));

        $html = $this->twig()->render('@SolidWorxPlatform/Profile/change_password.html.twig', [
            'form' => $this->service(ProfileTestKernel::FORM_FACTORY, FormFactoryInterface::class)->create(ChangePasswordType::class)->createView(),
            'user' => $this->user,
            'password_requirements' => $this->service(PasswordPolicyInterface::class, PasswordPolicyInterface::class)->requirements(),
        ]);

        self::assertStringContainsString('<a href="/reset-password">Forgot password?</a>', $html);
        self::assertStringContainsString('Current password', $html);
    }

    public function testTheEmailCarriesTheLinkAndWhenItExpires(): void
    {
        $html = $this->twig()->render('@SolidWorxPlatform/ResetPassword/email.html.twig', [
            'user' => $this->user,
            'resetUrl' => 'https://app.example.com/reset-password/reset/abc',
            'expiresAtMessageKey' => '%count% hour|%count% hours',
            'expiresAtMessageData' => [
                '%count%' => 1,
            ],
        ]);

        self::assertStringContainsString('href="https://app.example.com/reset-password/reset/abc"', $html);
        self::assertStringContainsString('This link will expire in 1 hour.', $html);
    }

    private function twig(): Environment
    {
        return $this->service('twig', Environment::class);
    }
}
