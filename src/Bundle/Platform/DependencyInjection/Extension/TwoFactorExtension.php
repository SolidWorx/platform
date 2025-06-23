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

use SolidWorx\Platform\PlatformBundle\Security\TwoFactor\TwoFactorFormRenderer;
use SolidWorx\Platform\PlatformBundle\Util\Time;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Config\Security\AccessControlConfig;
use Symfony\Config\Security\FirewallConfig;

final class TwoFactorExtension
{
    /**
     * @param array{name: string, base_template: string} $config
     */
    public static function enable(ContainerBuilder $container, array $config = []): void
    {
        $container
            ->setDefinition(
                'solidworx_platform.security.two_factor.form_renderer.email',
                (new Definition(TwoFactorFormRenderer::class))
                    ->addArgument(new Reference('twig'))
                    ->addArgument(new Parameter('scheb_two_factor.email.template'))
                    ->addArgument($config),
            );
        $container
            ->setDefinition(
                'solidworx_platform.security.two_factor.form_renderer.totp',
                (new Definition(TwoFactorFormRenderer::class))
                    ->addArgument(new Reference('twig'))
                    ->addArgument(new Parameter('scheb_two_factor.totp.template'))
                    ->addArgument($config),
            );

        $container->prependExtensionConfig('scheb_two_factor', [
            'security_tokens' => [
                UsernamePasswordToken::class,
                PostAuthenticationToken::class,
            ],
            'trusted_device' => [
                'enabled' => true,
                'lifetime' => Time::MONTH,
            ],
            'totp' => [
                'enabled' => true,
                'server_name' => $config['name'],
                'issuer' => $config['name'],
                'leeway' => 10,
                'template' => '@SolidWorxPlatform/Security/TwoFactor/totp.html.twig',
                'form_renderer' => 'solidworx_platform.security.two_factor.form_renderer.totp',
            ],
            'email' => [
                'enabled' => true,
                // 'sender_email' => 'no-reply@solidworx.co',
                'sender_name' => $config['name'],
                'digits' => 6,
                'template' => '@SolidWorxPlatform/Security/TwoFactor/email.html.twig',
                'form_renderer' => 'solidworx_platform.security.two_factor.form_renderer.email',
            ],
            'backup_codes' => [
                'enabled' => true,
            ],
        ]);
    }

    public static function configureSecurity(
        FirewallConfig $config,
        AccessControlConfig $accessControlConfig,
    ): void {
        $config
            ->twoFactor()
            ->authFormPath('2fa_login')
            ->checkPath('2fa_login_check')
            ->enableCsrf(true)
            ->alwaysUseDefaultTargetPath(true);

        $accessControlConfig
            ->path('^/2fa/resend-email')
            ->roles(['IS_AUTHENTICATED_2FA_IN_PROGRESS']);
        $accessControlConfig
            ->path('^/2fa')
            ->roles(['IS_AUTHENTICATED_2FA_IN_PROGRESS']);
    }
}
