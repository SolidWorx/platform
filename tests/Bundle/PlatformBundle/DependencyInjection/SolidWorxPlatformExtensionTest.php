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
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');

        new SolidWorxPlatformExtension($config)->load([$config], $container);

        return $container;
    }
}
