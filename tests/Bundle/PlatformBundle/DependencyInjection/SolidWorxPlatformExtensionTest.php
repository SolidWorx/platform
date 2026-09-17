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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfiguration;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\SolidWorxPlatformExtension;
use SolidWorx\Platform\PlatformBundle\Security\Voter\TenantCreationVoter;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(SolidWorxPlatformExtension::class)]
final class SolidWorxPlatformExtensionTest extends TestCase
{
    private const string PARAMETER = 'solidworx_platform.security.access_decision.strategies';

    public function testTheAttributesThePlatformDecidesItselfAreAlwaysSet(): void
    {
        $container = $this->load([]);

        self::assertSame(
            PlatformConfiguration::PLATFORM_ACCESS_DECISION_STRATEGIES,
            $container->getParameter(self::PARAMETER),
        );
    }

    /**
     * An application configuring the map is adding to it, not taking over: losing `TENANT_CREATE`
     * here would quietly stop a refusal from counting.
     */
    public function testAnApplicationsOwnAttributesAreAddedToThePlatformsOwn(): void
    {
        $container = $this->load([
            'security' => [
                'access_decision' => [
                    'strategies' => [
                        'INVITE_MEMBER' => 'consensus',
                    ],
                ],
            ],
        ]);

        self::assertSame(
            PlatformConfiguration::PLATFORM_ACCESS_DECISION_STRATEGIES + [
                'INVITE_MEMBER' => 'consensus',
            ],
            $container->getParameter(self::PARAMETER),
        );
    }

    public function testThePlatformKeepsItsOwnStrategyForItsOwnAttributes(): void
    {
        $container = $this->load([
            'security' => [
                'access_decision' => [
                    'strategies' => [
                        'TENANT_CREATE' => 'affirmative',
                    ],
                ],
            ],
        ]);

        self::assertSame(
            PlatformConfiguration::PLATFORM_ACCESS_DECISION_STRATEGIES,
            $container->getParameter(self::PARAMETER),
        );
    }

    public function testBuildParameterFallsBackToPlatformName(): void
    {
        $container = $this->load([
            'name' => 'Acme Billing',
        ]);

        $build = $container->getParameter('solidworx_platform.build');

        self::assertIsArray($build);
        self::assertSame('Acme Billing', $build['name']);
        self::assertSame('acme-billing', $build['binary_name']);
        self::assertSame('ACME_BILLING', $build['env_prefix']);
    }

    public function testBuildParameterKeepsExplicitIdentity(): void
    {
        $container = $this->load([
            'name' => 'Acme Billing',
            'build' => [
                'name' => 'Acme',
                'binary_name' => 'acme',
                'env_prefix' => 'ACMEBILL',
            ],
        ]);

        $build = $container->getParameter('solidworx_platform.build');

        self::assertIsArray($build);
        self::assertSame('Acme', $build['name']);
        self::assertSame('acme', $build['binary_name']);
        self::assertSame('ACMEBILL', $build['env_prefix']);
    }

    /**
     * With multi-tenancy off — the default — the container must still compile: nothing tenancy-only
     * may keep a reference to a service that {@see SolidWorxPlatformExtension::MULTI_TENANCY_SERVICES}
     * removed, or every application booting without multi-tenancy would fail to boot at all.
     */
    public function testTheContainerCompilesWithMultiTenancyDisabled(): void
    {
        $container = $this->load([]);

        // SecurityBundle is what normally keeps a voter alive, by collecting every
        // `security.voter`-tagged service into the access decision manager. Nothing in this bare
        // container consumes that tag, so without forcing the voter public here,
        // `RemoveUnusedDefinitionsPass` would quietly drop it — and the dangling constructor
        // argument that broke compilation in a real application — before autowiring's error could
        // surface.
        if ($container->hasDefinition(TenantCreationVoter::class)) {
            $container->getDefinition(TenantCreationVoter::class)->setPublic(true);
        }

        $container->compile();

        self::assertFalse($container->hasDefinition(TenantCreationVoter::class));
    }

    public function testTheVoterIsKeptWhenMultiTenancyIsEnabled(): void
    {
        $container = $this->load([
            'multi_tenancy' => [
                'enabled' => true,
            ],
        ]);

        self::assertTrue($container->hasDefinition(TenantCreationVoter::class));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.project_dir', '/tmp/app');

        new SolidWorxPlatformExtension($config)->load([$config], $container);

        return $container;
    }
}
