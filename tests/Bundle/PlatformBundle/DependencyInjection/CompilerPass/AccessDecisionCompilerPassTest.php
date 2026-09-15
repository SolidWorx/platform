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

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\CompilerPass\AccessDecisionCompilerPass;
use SolidWorx\Platform\PlatformBundle\Security\Authorization\PerAttributeAccessDecisionManager;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\AddSecurityVotersPass;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Strategy\AffirmativeStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * The pass rewrites a service Symfony owns, so what matters is that Symfony's own pass still works
 * on it — whichever order the two run in.
 */
#[CoversClass(AccessDecisionCompilerPass::class)]
final class AccessDecisionCompilerPassTest extends TestCase
{
    private const string MANAGER_ID = 'security.access.decision_manager';

    #[TestWith([true], 'platform pass first')]
    #[TestWith([false], 'Symfony pass first')]
    public function testTheVotersStillReachTheManager(bool $platformFirst): void
    {
        $container = $this->createContainer();

        $this->process($container, $platformFirst);

        $definition = $container->getDefinition(self::MANAGER_ID);

        self::assertSame(PerAttributeAccessDecisionManager::class, $definition->getClass());

        $voters = $definition->getArgument(0);
        self::assertInstanceOf(IteratorArgument::class, $voters);
        self::assertCount(1, $voters->getValues());
    }

    public function testTheApplicationsOwnStrategyStaysTheDefault(): void
    {
        $container = $this->createContainer();

        $this->process($container);

        $strategy = $container->getDefinition(self::MANAGER_ID)->getArgument(1);

        self::assertInstanceOf(Definition::class, $strategy);
        self::assertSame(AffirmativeStrategy::class, $strategy->getClass());
    }

    public function testTheConfiguredAttributesGetTheirOwnStrategy(): void
    {
        $container = $this->createContainer();

        $this->process($container);

        $strategies = $container->getDefinition(self::MANAGER_ID)->getArgument(2);

        self::assertIsArray($strategies);
        self::assertArrayHasKey('TENANT_CREATE', $strategies);
        self::assertInstanceOf(Definition::class, $strategies['TENANT_CREATE']);
        self::assertSame(UnanimousStrategy::class, $strategies['TENANT_CREATE']->getClass());
    }

    public function testItDoesNothingWithoutConfiguredAttributes(): void
    {
        $container = $this->createContainer(strategies: []);

        $this->process($container);

        self::assertSame(AccessDecisionManager::class, $container->getDefinition(self::MANAGER_ID)->getClass());
    }

    /**
     * An application that brought its own manager would silently stop honouring the attribute
     * strategies, which for a security decision has to be loud.
     */
    public function testItRefusesToBeIgnoredByACustomManager(): void
    {
        $container = $this->createContainer();
        $container->removeDefinition(self::MANAGER_ID);
        $container->setAlias(self::MANAGER_ID, 'app.my_access_decision_manager');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/security\.access_decision_manager\.service/');

        (new AccessDecisionCompilerPass())->process($container);
    }

    /**
     * @param array<string, string> $strategies
     */
    private function createContainer(array $strategies = [
        'TENANT_CREATE' => 'unanimous',
    ]): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('solidworx_platform.security.access_decision.strategies', $strategies);

        // Mirrors what SecurityBundle registers: an empty voter list, then the configured strategy.
        $container->register(self::MANAGER_ID, AccessDecisionManager::class)
            ->setArguments([[], new Definition(AffirmativeStrategy::class, [false])]);

        $container->register('app.voter', Voter::class)
            ->setAbstract(false)
            ->addTag('security.voter');

        return $container;
    }

    private function process(ContainerBuilder $container, bool $platformFirst = true): void
    {
        $passes = [new AccessDecisionCompilerPass(), new AddSecurityVotersPass()];

        if (! $platformFirst) {
            $passes = array_reverse($passes);
        }

        foreach ($passes as $pass) {
            self::assertInstanceOf(CompilerPassInterface::class, $pass);

            $pass->process($container);
        }
    }
}
