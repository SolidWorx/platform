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
use SolidWorx\Platform\PlatformBundle\Repository\ResetPasswordRequestRepository;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Points symfonycasts/reset-password-bundle at the platform's repository when the application
 * config still names the bundle's placeholder.
 *
 * Symfony Flex installs the bundle's recipe even though the bundle only arrives as a platform
 * dependency, and that recipe writes `config/packages/reset_password.yaml` with the fake
 * repository — which wins over the platform's prepended config and makes every reset fail. The
 * placeholder is never a valid choice, so it is replaced; any other repository is left alone.
 */
final class ResetPasswordRepositoryPass implements CompilerPassInterface
{
    private const string FAKE_REPOSITORY = 'symfonycasts.reset_password.fake_request_repository';

    /**
     * The services the bundle hands the repository to, and the argument it is passed as.
     */
    private const array CONSUMERS = [
        'symfonycasts.reset_password.helper' => 2,
        'symfonycasts.reset_password.cleaner' => 0,
    ];

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        // Only registered while `platform.security.reset_password.enabled` is true.
        if (! $container->hasDefinition(ResetPasswordRequestRepository::class)) {
            return;
        }

        foreach (self::CONSUMERS as $id => $index) {
            if (! $container->hasDefinition($id)) {
                continue;
            }

            $definition = $container->getDefinition($id);
            $repository = $definition->getArgument($index);

            if ($repository instanceof Reference && (string) $repository === self::FAKE_REPOSITORY) {
                $definition->replaceArgument($index, new Reference(ResetPasswordRequestRepository::class));
            }
        }
    }
}
