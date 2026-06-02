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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\DependencyInjection\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResendTwoFactorCode;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension\TwoFactorExtension;

#[CoversClass(TwoFactorExtension::class)]
final class TwoFactorExtensionTest extends TestCase
{
    public function testSecurityConfigReturnsFirewallAndBothAccessControlRules(): void
    {
        self::assertSame(
            [
                'firewall' => [
                    'auth_form_path' => '2fa_login',
                    'check_path' => '2fa_login_check',
                    'enable_csrf' => true,
                    'always_use_default_target_path' => true,
                ],
                'access_control' => [
                    [
                        'path' => '^/2fa/resend',
                        'roles' => ['IS_AUTHENTICATED_2FA_IN_PROGRESS'],
                    ],
                    [
                        'path' => '^/2fa',
                        'roles' => ['IS_AUTHENTICATED_2FA_IN_PROGRESS'],
                    ],
                ],
            ],
            TwoFactorExtension::securityConfig(),
        );
    }

    public function testResendRuleMatchesTheActualRouteAndPrecedesTheBroaderRule(): void
    {
        $accessControl = TwoFactorExtension::securityConfig()['access_control'];

        // The resend rule is derived from the controller's route path, so it always matches the
        // real route. It must precede ^/2fa, otherwise the broader prefix would shadow it (first
        // match wins in access_control evaluation).
        self::assertCount(2, $accessControl);
        self::assertSame('^' . ResendTwoFactorCode::PATH, $accessControl[0]['path']);
        self::assertSame('^/2fa/resend', $accessControl[0]['path']);
        self::assertSame('^/2fa', $accessControl[1]['path']);
    }
}
