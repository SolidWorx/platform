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

use SolidWorx\Platform\PlatformBundle\Model\User;
use Symfony\Config\Security\FirewallConfig;
use Symfony\Config\SecurityConfig;

final class LoginExtension
{
    public static function configureDefaultFormLogin(
        SecurityConfig $config,
        bool $enableTwoFactor = false,
    ): FirewallConfig {
        $config
            ->passwordHasher(User::class)
            ->algorithm('auto');

        $mainFirewallConfig = $config
            ->firewall('main')
            ->pattern('^/')
            ->entryPoint('form_login')
            ->provider('platform_user')
            ->lazy(true);

        $mainFirewallConfig
            ->rememberMe()
            ->lifetime(60 * 60 * 24 * 7) // 7 days
            ->path('/')
            ->domain(null);

        $mainFirewallConfig
            ->formLogin()
            ->enableCsrf(true)
            ->loginPath('/login')
            ->checkPath('_login_check')
            ->provider('platform_user')
            ->alwaysUseDefaultTargetPath(true);

        $mainFirewallConfig
            ->logout()
            ->path('/logout')
            ->clearSiteData(['cookies', 'storage', 'executionContexts'])
            ->invalidateSession(true)
            ->enableCsrf(true)
            ->target('/');

        $mainFirewallConfig
            ->loginThrottling()
            ->maxAttempts(5)
            ->interval('15 minutes');

        if ($enableTwoFactor) {
            TwoFactorExtension::configureSecurity($mainFirewallConfig, $config->accessControl());
        }

        return $mainFirewallConfig;
    }
}
