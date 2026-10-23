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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\CompilerPass\ResetPasswordRepositoryPass;
use SolidWorx\Platform\PlatformBundle\Repository\ResetPasswordRequestRepository;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(ResetPasswordRepositoryPass::class)]
final class ResetPasswordRepositoryPassTest extends TestCase
{
    /**
     * What `config/packages/reset_password.yaml` from the bundle's Flex recipe leaves behind.
     */
    public function testTheRecipesPlaceholderIsReplacedWithThePlatformRepository(): void
    {
        $container = $this->container('symfonycasts.reset_password.fake_request_repository', withPlatformRepository: true);

        new ResetPasswordRepositoryPass()->process($container);

        self::assertEquals(new Reference(ResetPasswordRequestRepository::class), $container->getDefinition('symfonycasts.reset_password.helper')->getArgument(2));
        self::assertEquals(new Reference(ResetPasswordRequestRepository::class), $container->getDefinition('symfonycasts.reset_password.cleaner')->getArgument(0));
    }

    public function testARepositoryOfTheApplicationsOwnIsKept(): void
    {
        $container = $this->container('app.reset_password_repository', withPlatformRepository: true);

        new ResetPasswordRepositoryPass()->process($container);

        self::assertEquals(new Reference('app.reset_password_repository'), $container->getDefinition('symfonycasts.reset_password.helper')->getArgument(2));
    }

    public function testNothingChangesWhileResetPasswordIsDisabled(): void
    {
        $container = $this->container('symfonycasts.reset_password.fake_request_repository', withPlatformRepository: false);

        new ResetPasswordRepositoryPass()->process($container);

        self::assertEquals(new Reference('symfonycasts.reset_password.fake_request_repository'), $container->getDefinition('symfonycasts.reset_password.helper')->getArgument(2));
    }

    private function container(string $repository, bool $withPlatformRepository): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('symfonycasts.reset_password.helper')
            ->setArguments(['generator', 'cleaner', new Reference($repository), 3600, 900]);
        $container->register('symfonycasts.reset_password.cleaner')
            ->setArguments([new Reference($repository), true]);

        if ($withPlatformRepository) {
            $container->register(ResetPasswordRequestRepository::class);
        }

        return $container;
    }
}
