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

use SolidWorx\Platform\PlatformBundle\Controller\Security\ResendTwoFactorCode;
use SolidWorx\Platform\PlatformBundle\Security\TwoFactor\TwoFactorFormRenderer;
use SolidWorx\Platform\PlatformBundle\Util\Time;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

final class TwoFactorExtension
{
    /**
     * @param array{name: string, base_template: string} $config
     */
    public static function enable(ContainerBuilder $container, array $config): void
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

    /**
     * Returns the two-factor security fragment as a YAML-like array-shape, suitable for the
     * Symfony 7.4 array-shape config format (`App::config([...])`).
     *
     * The `firewall` key holds the `two_factor` settings to merge into a firewall, and
     * `access_control` holds the access-control rules to append (order matters — these
     * should come before any app-specific rules).
     *
     * Both 2FA paths are registered, with the more specific resend-code rule (the path of
     * {@see ResendTwoFactorCode}, `^/2fa/resend`) **before** the broader `^/2fa` rule.
     * Access-control rules are evaluated top-to-bottom and the first match wins, so the specific
     * rule must precede the prefix it shares; otherwise `^/2fa` would shadow it. The resend path
     * is derived from {@see ResendTwoFactorCode::PATH} so the rule always matches the actual
     * route, even if that route ever moves.
     *
     * @return array{
     *     firewall: array{auth_form_path: string, check_path: string, enable_csrf: bool, always_use_default_target_path: bool},
     *     access_control: list<array{path: string, roles: list<string>}>,
     * }
     */
    public static function securityConfig(): array
    {
        return [
            'firewall' => [
                'auth_form_path' => '2fa_login',
                'check_path' => '2fa_login_check',
                'enable_csrf' => true,
                'always_use_default_target_path' => true,
            ],
            'access_control' => [
                [
                    'path' => '^' . ResendTwoFactorCode::PATH,
                    'roles' => ['IS_AUTHENTICATED_2FA_IN_PROGRESS'],
                ],
                [
                    'path' => '^/2fa',
                    'roles' => ['IS_AUTHENTICATED_2FA_IN_PROGRESS'],
                ],
            ],
        ];
    }
}
