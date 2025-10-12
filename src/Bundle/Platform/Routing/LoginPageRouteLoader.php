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
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[AutoconfigureTag(name: 'routing.loader', attributes: [
    'priority' => -100,
])]
class LoginPageRouteLoader extends Loader
{
    public function __construct(
        private readonly iterable $authenticators,
        ?string $env = null,
    ) {
        parent::__construct($env);
    }

    #[Override]
    public function load(mixed $resource, ?string $type = null): mixed
    {
        $collection = new RouteCollection();

        foreach ($this->authenticators as $id => $authenticator) {
            if (str_starts_with((string) $authenticator['check_path'], '/')) {
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

        return $collection;
    }

    #[Override]
    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $resource === '.' && $type === '_solidworx_platform_auth_routes';
    }
}
