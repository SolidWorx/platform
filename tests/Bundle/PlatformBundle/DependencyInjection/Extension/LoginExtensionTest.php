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
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfigState;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension\LoginExtension;
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\PlatformBundle\Util\Time;

#[CoversClass(LoginExtension::class)]
final class LoginExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        PlatformConfigState::clear();
    }

    protected function tearDown(): void
    {
        PlatformConfigState::clear();
    }

    public function testReturnsSecurityWrappedDefaultsWithoutTwoFactor(): void
    {
        // Using Time::WEEK in the expectation also proves the helper sources the lifetime
        // from the constant (604800, 7 days) rather than a duplicated literal.
        self::assertSame(
            [
                'password_hashers' => [
                    User::class => [
                        'algorithm' => 'auto',
                    ],
                ],
                'firewalls' => [
                    'main' => self::defaultMainFirewall(),
                ],
                'access_control' => [],
            ],
            LoginExtension::defaultFormLoginConfig()['security'],
        );
    }

    public function testTwoFactorIsAbsentWhenPlatformConfigDisablesIt(): void
    {
        PlatformConfigState::set([
            'security' => [
                'two_factor' => [
                    'enabled' => false,
                ],
            ],
        ]);

        self::assertSame(
            [
                'password_hashers' => [
                    User::class => [
                        'algorithm' => 'auto',
                    ],
                ],
                'firewalls' => [
                    'main' => self::defaultMainFirewall(),
                ],
                'access_control' => [],
            ],
            LoginExtension::defaultFormLoginConfig()['security'],
        );
    }

    public function testTwoFactorIsAutoDetectedFromPlatformConfig(): void
    {
        PlatformConfigState::set([
            'security' => [
                'two_factor' => [
                    'enabled' => true,
                ],
            ],
        ]);

        $main = self::defaultMainFirewall();
        $main['two_factor'] = [
            'auth_form_path' => '2fa_login',
            'check_path' => '2fa_login_check',
            'enable_csrf' => true,
            'always_use_default_target_path' => true,
        ];

        self::assertSame(
            [
                'password_hashers' => [
                    User::class => [
                        'algorithm' => 'auto',
                    ],
                ],
                'firewalls' => [
                    'main' => $main,
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
            LoginExtension::defaultFormLoginConfig()['security'],
        );
    }

    public function testOverridesMergeScalarsAndNestedKeysPreservingSiblings(): void
    {
        $main = self::defaultMainFirewall();
        $main['lazy'] = false;
        $main['form_login']['default_target_path'] = '_dashboard';

        self::assertSame(
            [
                'password_hashers' => [
                    User::class => [
                        'algorithm' => 'auto',
                    ],
                ],
                'firewalls' => [
                    'main' => $main,
                ],
                'access_control' => [],
            ],
            LoginExtension::defaultFormLoginConfig([
                'firewalls' => [
                    'main' => [
                        'lazy' => false,
                        'form_login' => [
                            'default_target_path' => '_dashboard',
                        ],
                    ],
                ],
            ])['security'],
        );
    }

    public function testOverridesAddNewFirewallsProvidersAndAuthenticators(): void
    {
        $main = self::defaultMainFirewall();
        $main['custom_authenticators'] = ['App\\Security\\OAuthAuthenticator'];

        self::assertSame(
            [
                'password_hashers' => [
                    User::class => [
                        'algorithm' => 'auto',
                    ],
                ],
                'firewalls' => [
                    'main' => $main,
                    'api' => [
                        'pattern' => '^/api',
                        'stateless' => true,
                    ],
                ],
                'access_control' => [],
                'providers' => [
                    'app_users' => [
                        'id' => 'App\\Security\\UserProvider',
                    ],
                ],
            ],
            LoginExtension::defaultFormLoginConfig([
                'firewalls' => [
                    'main' => [
                        'custom_authenticators' => ['App\\Security\\OAuthAuthenticator'],
                    ],
                    'api' => [
                        'pattern' => '^/api',
                        'stateless' => true,
                    ],
                ],
                'providers' => [
                    'app_users' => [
                        'id' => 'App\\Security\\UserProvider',
                    ],
                ],
            ])['security'],
        );
    }

    public function testAccessControlOverridesAppendAfterPlatformRules(): void
    {
        PlatformConfigState::set([
            'security' => [
                'two_factor' => [
                    'enabled' => true,
                ],
            ],
        ]);

        $main = self::defaultMainFirewall();
        $main['two_factor'] = [
            'auth_form_path' => '2fa_login',
            'check_path' => '2fa_login_check',
            'enable_csrf' => true,
            'always_use_default_target_path' => true,
        ];

        self::assertSame(
            [
                'password_hashers' => [
                    User::class => [
                        'algorithm' => 'auto',
                    ],
                ],
                'firewalls' => [
                    'main' => $main,
                ],
                'access_control' => [
                    // platform 2FA rules first, then the app's rules appended
                    [
                        'path' => '^/2fa/resend',
                        'roles' => ['IS_AUTHENTICATED_2FA_IN_PROGRESS'],
                    ],
                    [
                        'path' => '^/2fa',
                        'roles' => ['IS_AUTHENTICATED_2FA_IN_PROGRESS'],
                    ],
                    [
                        'path' => '^/admin',
                        'roles' => ['ROLE_ADMIN'],
                    ],
                    [
                        'path' => '^/',
                        'roles' => ['ROLE_USER'],
                    ],
                ],
            ],
            LoginExtension::defaultFormLoginConfig([
                'access_control' => [
                    [
                        'path' => '^/admin',
                        'roles' => ['ROLE_ADMIN'],
                    ],
                    [
                        'path' => '^/',
                        'roles' => ['ROLE_USER'],
                    ],
                ],
            ])['security'],
        );
    }

    /**
     * The default `main` firewall the helper produces (without two-factor).
     *
     * @return array{
     *     pattern: string,
     *     entry_point: string,
     *     provider: string,
     *     lazy: bool,
     *     remember_me: array{lifetime: int, path: string, domain: string|null},
     *     form_login: array{provider: string, login_path: string, check_path: string, enable_csrf: bool, always_use_default_target_path: bool},
     *     logout: array{path: string, target: string, clear_site_data: list<string>, invalidate_session: bool, enable_csrf: bool},
     *     login_throttling: array{max_attempts: int, interval: string},
     * }
     */
    private static function defaultMainFirewall(): array
    {
        return [
            'pattern' => '^/',
            'entry_point' => 'form_login',
            'provider' => 'platform_user',
            'lazy' => true,
            'remember_me' => [
                'lifetime' => Time::WEEK,
                'path' => '/',
                'domain' => null,
            ],
            'form_login' => [
                'provider' => 'platform_user',
                'login_path' => '/login',
                'check_path' => '_login_check',
                'enable_csrf' => true,
                'always_use_default_target_path' => true,
            ],
            'logout' => [
                'path' => '/logout',
                'target' => '/',
                'clear_site_data' => ['cookies', 'storage', 'executionContexts'],
                'invalidate_session' => true,
                'enable_csrf' => true,
            ],
            'login_throttling' => [
                'max_attempts' => 5,
                'interval' => '15 minutes',
            ],
        ];
    }
}
