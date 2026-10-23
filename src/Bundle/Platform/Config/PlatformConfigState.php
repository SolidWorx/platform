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

namespace SolidWorx\Platform\PlatformBundle\Config;

/**
 * Holds the parsed `platform:` configuration section during container compilation.
 *
 * The platform kernel publishes the config here at boot — before `config/packages/*.php`
 * is evaluated — so that helpers such as
 * {@see \SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension\LoginExtension::defaultFormLoginConfig()}
 * can read platform settings (e.g. whether two-factor authentication is enabled) while the
 * container is being built.
 *
 * This is compile-time state only: a warm (cached) container is never recompiled, so this is
 * never touched at runtime. It is cleared at the end of the build by
 * {@see \SolidWorx\Platform\PlatformBundle\DependencyInjection\CompilerPass\ClearPlatformConfigStatePass}.
 */
final class PlatformConfigState
{
    /**
     * @var array<array-key, mixed>|null
     */
    private static ?array $config = null;

    /**
     * @param array<array-key, mixed> $config The `platform:` config section.
     */
    public static function set(array $config): void
    {
        self::$config = $config;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public static function get(): ?array
    {
        return self::$config;
    }

    /**
     * Whether two-factor authentication is enabled in the platform configuration.
     *
     * Returns false when no platform configuration has been published (e.g. a container built
     * without the platform kernel).
     */
    public static function isTwoFactorEnabled(): bool
    {
        return self::isSecurityFeatureEnabled('two_factor');
    }

    /**
     * Whether password reset is enabled in the platform configuration. False when no platform
     * configuration has been published.
     */
    public static function isResetPasswordEnabled(): bool
    {
        return self::isSecurityFeatureEnabled('reset_password');
    }

    public static function clear(): void
    {
        self::$config = null;
    }

    private static function isSecurityFeatureEnabled(string $feature): bool
    {
        $security = self::$config['security'] ?? null;

        if (! is_array($security)) {
            return false;
        }

        $config = $security[$feature] ?? null;

        if (! is_array($config)) {
            return false;
        }

        return ($config['enabled'] ?? false) === true;
    }
}
