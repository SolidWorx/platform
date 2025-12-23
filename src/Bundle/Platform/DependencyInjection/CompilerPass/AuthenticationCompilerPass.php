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

namespace SolidWorx\Platform\PlatformBundle\DependencyInjection\CompilerPass;

use Override;
use SolidWorx\Platform\PlatformBundle\Routing\LoginPageRouteLoader;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AuthenticationCompilerPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition(LoginPageRouteLoader::class)) {
            $this->registerLoginRouteLoader($container);
        }

        if ($container->hasDefinition('security.user.provider.concrete.platform_user')) {
            $definition = $container->getDefinition('security.user.provider.concrete.platform_user');
            $definition->setArgument(0, $container->getParameter('solidworx_platform.models.user'));
        }
    }

    private function registerLoginRouteLoader(ContainerBuilder $container): void
    {
        $routeLoader = $container->getDefinition(LoginPageRouteLoader::class);
        $firewalls = $container->getParameter('security.firewalls');

        $authenticators = [];

        foreach ($firewalls as $firewall) {
            if (!$container->hasDefinition('security.authenticator.form_login.' . $firewall)) {
                continue;
            }

            $authenticator = $container->getDefinition('security.authenticator.form_login.' . $firewall);
            $authenticators[$firewall] = $authenticator->getArgument(4) + [
                    'remember_me_parameter' => null,
                    'always_remember_me' => false,
                ];

            if ($container->hasDefinition('security.authenticator.remember_me_handler.' . $firewall)) {
                $rememberMeArguments = $container->getDefinition('security.authenticator.remember_me_handler.' . $firewall)->getArgument(3);

                $authenticators[$firewall]['remember_me_parameter'] = $rememberMeArguments['remember_me_parameter'];
                $authenticators[$firewall]['always_remember_me'] = $rememberMeArguments['always_remember_me'];
            }
        }

        $routeLoader->addArgument(new IteratorArgument($authenticators));
    }
}
