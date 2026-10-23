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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResendTwoFactorCode;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResendVerificationEmail;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword\CheckResetPasswordEmail;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword\RequestResetPassword;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword\ResetPassword;
use SolidWorx\Platform\PlatformBundle\Controller\Security\TwoFactorConfiguration;
use SolidWorx\Platform\PlatformBundle\Controller\Security\VerifyEmail;
use SolidWorx\Platform\PlatformBundle\Routing\LoginPageRouteLoader;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(LoginPageRouteLoader::class)]
final class LoginPageRouteLoaderTest extends TestCase
{
    public function testEmailVerificationRoutesAreAddedWhenEnabled(): void
    {
        $routes = $this->load(emailVerificationEnabled: true);

        $this->assertSame(VerifyEmail::PATH, $routes->get(VerifyEmail::ROUTE_NAME)?->getPath());
        $this->assertSame(['GET'], $routes->get(VerifyEmail::ROUTE_NAME)->getMethods());
        $this->assertSame(VerifyEmail::class, $routes->get(VerifyEmail::ROUTE_NAME)->getDefault('_controller'));

        $this->assertSame(ResendVerificationEmail::PATH, $routes->get(ResendVerificationEmail::ROUTE_NAME)?->getPath());
        $this->assertSame(['POST'], $routes->get(ResendVerificationEmail::ROUTE_NAME)->getMethods());
    }

    public function testEmailVerificationRoutesAreOmittedWhenDisabled(): void
    {
        $routes = $this->load(emailVerificationEnabled: false);

        $this->assertNull($routes->get(VerifyEmail::ROUTE_NAME));
        $this->assertNull($routes->get(ResendVerificationEmail::ROUTE_NAME));
    }

    public function testTwoFactorRoutesAreAddedWhenEnabled(): void
    {
        $routes = $this->load(twoFactorEnabled: true);

        $this->assertSame('/2fa', $routes->get('2fa_login')?->getPath());
        $this->assertSame('scheb_two_factor.form_controller::form', $routes->get('2fa_login')->getDefault('_controller'));
        $this->assertSame('/2fa_check', $routes->get('2fa_login_check')?->getPath());
        $this->assertSame(ResendTwoFactorCode::PATH, $routes->get(ResendTwoFactorCode::ROUTE_NAME)?->getPath());
        $this->assertSame(TwoFactorConfiguration::PATH, $routes->get(TwoFactorConfiguration::ROUTE_NAME)?->getPath());
        $this->assertSame(['GET'], $routes->get(TwoFactorConfiguration::ROUTE_NAME)->getMethods());
    }

    public function testTwoFactorRoutesAreOmittedWhenDisabled(): void
    {
        $routes = $this->load(twoFactorEnabled: false);

        $this->assertNull($routes->get('2fa_login'));
        $this->assertNull($routes->get('2fa_login_check'));
        $this->assertNull($routes->get(ResendTwoFactorCode::ROUTE_NAME));
        $this->assertNull($routes->get(TwoFactorConfiguration::ROUTE_NAME));
    }

    public function testResetPasswordRoutesAreAddedWhenEnabled(): void
    {
        $routes = $this->load(resetPasswordEnabled: true);

        $this->assertSame(RequestResetPassword::PATH, $routes->get(RequestResetPassword::ROUTE_NAME)?->getPath());
        $this->assertSame(['GET', 'POST'], $routes->get(RequestResetPassword::ROUTE_NAME)->getMethods());
        $this->assertSame(CheckResetPasswordEmail::PATH, $routes->get(CheckResetPasswordEmail::ROUTE_NAME)?->getPath());
        $this->assertSame(['GET'], $routes->get(CheckResetPasswordEmail::ROUTE_NAME)->getMethods());
        $this->assertSame(ResetPassword::PATH, $routes->get(ResetPassword::ROUTE_NAME)?->getPath());
        $this->assertSame(ResetPassword::class, $routes->get(ResetPassword::ROUTE_NAME)->getDefault('_controller'));
    }

    /**
     * The token is optional so the page can be served again once it has been moved into the session.
     */
    public function testTheResetRouteWorksWithoutAToken(): void
    {
        $route = $this->load(resetPasswordEnabled: true)->get(ResetPassword::ROUTE_NAME);

        $this->assertNotNull($route);
        $this->assertTrue($route->hasDefault('token'));
        $this->assertNull($route->getDefault('token'));
    }

    public function testResetPasswordRoutesAreOmittedWhenDisabled(): void
    {
        $routes = $this->load(resetPasswordEnabled: false);

        $this->assertNull($routes->get(RequestResetPassword::ROUTE_NAME));
        $this->assertNull($routes->get(CheckResetPasswordEmail::ROUTE_NAME));
        $this->assertNull($routes->get(ResetPassword::ROUTE_NAME));
    }

    private function load(bool $emailVerificationEnabled = false, bool $twoFactorEnabled = false, bool $resetPasswordEnabled = false): RouteCollection
    {
        $routes = new LoginPageRouteLoader([], $emailVerificationEnabled, $twoFactorEnabled, $resetPasswordEnabled)->load('.', '_solidworx_platform_auth_routes');
        $this->assertInstanceOf(RouteCollection::class, $routes);

        return $routes;
    }
}
