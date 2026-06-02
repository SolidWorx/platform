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

namespace SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension;

use SolidWorx\Platform\PlatformBundle\Config\PlatformConfigState;
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\PlatformBundle\Util\Time;
use function array_is_list;
use function is_array;

final class LoginExtension
{
    /**
     * Returns the platform's complete default `security` configuration, ready to hand straight
     * to the Symfony 7.4 array-shape config format.
     *
     * The common case needs no arguments — the configuration follows `platform.yaml`, including
     * two-factor authentication, which is enabled automatically when
     * `platform.security.two_factor.enabled` is true (read from {@see PlatformConfigState}). Drop
     * it into `config/packages/security.php` as:
     *
     * ```php
     * namespace Symfony\Component\DependencyInjection\Loader\Configurator;
     *
     * use SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension\LoginExtension;
     *
     * return App::config(LoginExtension::defaultFormLoginConfig());
     * ```
     *
     * Provide `$overrides` only for app-specific settings — they are deep-merged onto the
     * platform defaults:
     *
     * ```php
     * return App::config(LoginExtension::defaultFormLoginConfig([
     *     'firewalls' => [
     *         'main' => [
     *             'custom_authenticators' => [OAuthAuthenticator::class],
     *             'form_login' => ['default_target_path' => '_select_company'],
     *         ],
     *         'api' => [...],
     *     ],
     *     'access_control' => [
     *         ['path' => '^/admin', 'roles' => ['ROLE_ADMIN']],
     *     ],
     * ]));
     * ```
     *
     * Merge rules ({@see self::mergeSecurityConfig()}): associative arrays merge by key, scalars
     * are overwritten, and sequential lists are concatenated (defaults first). The list rule is
     * why your `access_control` rules land *after* the platform's 2FA rules — `access_control` is
     * evaluated top-to-bottom, so platform rules keep precedence while your rules still apply.
     *
     * @param array{
     *     password_hashers?: array<class-string, mixed>,
     *     providers?: array<string, mixed>,
     *     firewalls?: array<string, mixed>,
     *     access_control?: list<array<string, mixed>>,
     *     role_hierarchy?: array<string, list<string>|string>,
     * } $overrides
     *
     * @return array{security: array<array-key, mixed>}
     */
    public static function defaultFormLoginConfig(array $overrides = []): array
    {
        $main = [
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

        $accessControl = [];

        if (PlatformConfigState::isTwoFactorEnabled()) {
            $twoFactor = TwoFactorExtension::securityConfig();
            $main['two_factor'] = $twoFactor['firewall'];
            $accessControl = $twoFactor['access_control'];
        }

        $security = [
            'password_hashers' => [
                User::class => [
                    'algorithm' => 'auto',
                ],
            ],
            'firewalls' => [
                'main' => $main,
            ],
            'access_control' => $accessControl,
        ];

        return [
            'security' => self::mergeSecurityConfig($security, $overrides),
        ];
    }

    /**
     * Deep-merges `$overrides` onto `$defaults`:
     *
     * - associative arrays are merged by key (recursively);
     * - sequential lists are concatenated, `$defaults` first then `$overrides`;
     * - scalars, mismatched types and new keys take the `$overrides` value.
     *
     * This differs from {@see array_replace_recursive()}, which merges lists by index and would
     * therefore clobber ordered lists such as `access_control`.
     *
     * @param array<array-key, mixed> $defaults
     * @param array<array-key, mixed> $overrides
     *
     * @return array<array-key, mixed>
     */
    private static function mergeSecurityConfig(array $defaults, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            $existing = $defaults[$key] ?? null;

            if (is_array($existing) && is_array($value)) {
                $bothLists = array_is_list($existing) && array_is_list($value);

                if ($bothLists) {
                    $defaults[$key] = [...$existing, ...$value];

                    continue;
                }

                if (! array_is_list($existing) && ! array_is_list($value)) {
                    $defaults[$key] = self::mergeSecurityConfig($existing, $value);

                    continue;
                }
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }
}
