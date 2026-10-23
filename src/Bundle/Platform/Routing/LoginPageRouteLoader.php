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

namespace SolidWorx\Platform\PlatformBundle\Routing;

use InvalidArgumentException;
use Override;
use SolidWorx\Platform\PlatformBundle\Controller\Security\Login;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResendTwoFactorCode;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResendVerificationEmail;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword\CheckResetPasswordEmail;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword\RequestResetPassword;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword\ResetPassword;
use SolidWorx\Platform\PlatformBundle\Controller\Security\TwoFactorConfiguration;
use SolidWorx\Platform\PlatformBundle\Controller\Security\VerifyEmail;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[AutoconfigureTag(name: 'routing.loader', attributes: [
    'priority' => -100,
])]
class LoginPageRouteLoader extends Loader
{
    /**
     * @param iterable<string, array{
     *     check_path: string,
     *     login_path: string,
     *     username_parameter: string,
     *     password_parameter: string,
     *     csrf_parameter: string,
     *     csrf_token_id: string,
     *     enable_csrf: bool,
     *     remember_me_parameter: string|null,
     *     always_remember_me: bool,
     * }> $authenticators
     */
    public function __construct(
        private readonly iterable $authenticators,
        // The email verification, 2FA and password reset routes only exist while the feature,
        // and the bundle behind it, is on.
        #[Autowire(param: 'solidworx_platform.security.email_verification.enabled')]
        private readonly bool $emailVerificationEnabled = false,
        #[Autowire(param: 'solidworx_platform.security.two_factor.enabled')]
        private readonly bool $twoFactorEnabled = false,
        #[Autowire(param: 'solidworx_platform.security.reset_password.enabled')]
        private readonly bool $resetPasswordEnabled = false,
        ?string $env = null,
    ) {
        parent::__construct($env);
    }

    #[Override]
    public function load(mixed $resource, ?string $type = null): mixed
    {
        $collection = new RouteCollection();

        foreach ($this->authenticators as $id => $authenticator) {
            if (str_starts_with($authenticator['check_path'], '/')) {
                $collection->add(
                    $checkPath = '_login_' . $id . '_check_path',
                    new Route(
                        path: $authenticator['check_path'],
                        methods: ['POST'],
                    )
                );
            } else {

                if ($authenticator['check_path'] === '_login_' . $id) {
                    throw new InvalidArgumentException('The "check_path" for the form login authenticator cannot be "_login_' . $id . '" as this is reserved by the system. Please change it to something else.');
                }

                $collection->add(
                    $checkPath = $authenticator['check_path'],
                    new Route(
                        path: '/' . $authenticator['check_path'],
                        methods: ['POST'],
                    )
                );
            }

            $collection->add(
                '_login_' . $id,
                new Route(
                    path: $authenticator['login_path'],
                    defaults: [
                        '_controller' => Login::class,
                        '_form_login_options' => [
                            'username_parameter' => $authenticator['username_parameter'],
                            'password_parameter' => $authenticator['password_parameter'],
                            'csrf_parameter' => $authenticator['csrf_parameter'],
                            'csrf_token_id' => $authenticator['csrf_token_id'],
                            'enable_csrf' => $authenticator['enable_csrf'],
                            'check_path' => $checkPath,
                            'remember_me_parameter' => $authenticator['remember_me_parameter'],
                            'always_remember_me' => $authenticator['always_remember_me'],
                        ],
                    ],
                ),
            );

        }

        if ($this->emailVerificationEnabled) {
            $collection->add(VerifyEmail::ROUTE_NAME, new Route(
                path: VerifyEmail::PATH,
                defaults: [
                    '_controller' => VerifyEmail::class,
                ],
                methods: ['GET'],
            ));

            $collection->add(ResendVerificationEmail::ROUTE_NAME, new Route(
                path: ResendVerificationEmail::PATH,
                defaults: [
                    '_controller' => ResendVerificationEmail::class,
                ],
                methods: ['POST'],
            ));
        }

        if ($this->twoFactorEnabled) {
            $collection->add('2fa_login', new Route(
                path: '/2fa',
                defaults: [
                    '_controller' => 'scheb_two_factor.form_controller::form',
                ],
            ));

            $collection->add('2fa_login_check', new Route(path: '/2fa_check'));

            $collection->add(ResendTwoFactorCode::ROUTE_NAME, new Route(
                path: ResendTwoFactorCode::PATH,
                defaults: [
                    '_controller' => ResendTwoFactorCode::class,
                ],
            ));

            $collection->add(TwoFactorConfiguration::ROUTE_NAME, new Route(
                path: TwoFactorConfiguration::PATH,
                defaults: [
                    '_controller' => TwoFactorConfiguration::class,
                ],
                methods: ['GET'],
            ));
        }

        if ($this->resetPasswordEnabled) {
            $collection->add(RequestResetPassword::ROUTE_NAME, new Route(
                path: RequestResetPassword::PATH,
                defaults: [
                    '_controller' => RequestResetPassword::class,
                ],
                methods: ['GET', 'POST'],
            ));

            $collection->add(CheckResetPasswordEmail::ROUTE_NAME, new Route(
                path: CheckResetPasswordEmail::PATH,
                defaults: [
                    '_controller' => CheckResetPasswordEmail::class,
                ],
                methods: ['GET'],
            ));

            $collection->add(ResetPassword::ROUTE_NAME, new Route(
                path: ResetPassword::PATH,
                defaults: [
                    '_controller' => ResetPassword::class,
                    'token' => null,
                ],
                methods: ['GET', 'POST'],
            ));
        }

        return $collection;
    }

    #[Override]
    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $resource === '.' && $type === '_solidworx_platform_auth_routes';
    }
}
