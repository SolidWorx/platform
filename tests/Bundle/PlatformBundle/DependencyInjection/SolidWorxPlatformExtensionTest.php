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
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword\CheckResetPasswordEmail;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword\RequestResetPassword;
use SolidWorx\Platform\PlatformBundle\Controller\Security\ResetPassword\ResetPassword;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\SolidWorxPlatformExtension;
use SolidWorx\Platform\PlatformBundle\Repository\ResetPasswordRequestRepository;
use SolidWorx\Platform\PlatformBundle\Security\ResetPassword\ResetPasswordMailer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

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

    public function testWithResetPasswordDisabledNothingBehindItIsRegistered(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->getParameter('solidworx_platform.security.reset_password.enabled'));
        self::assertFalse($container->hasDefinition(RequestResetPassword::class));
        self::assertFalse($container->hasDefinition(CheckResetPasswordEmail::class));
        self::assertFalse($container->hasDefinition(ResetPassword::class));
        self::assertFalse($container->hasDefinition(ResetPasswordMailer::class));
        self::assertFalse($container->hasDefinition(ResetPasswordRequestRepository::class));
    }

    public function testWithResetPasswordEnabledTheRequestFormIsRateLimited(): void
    {
        $container = $this->load([
            'security' => [
                'reset_password' => [
                    'enabled' => true,
                ],
            ],
        ]);

        self::assertTrue($container->hasDefinition(ResetPassword::class));
        self::assertTrue($container->getDefinition(ResetPasswordMailer::class)->isAutowired());
        self::assertEquals(
            new Reference('limiter.solidworx_platform_reset_password'),
            $container->getDefinition(RequestResetPassword::class)->getArgument('$limiter'),
        );
    }

    public function testTheRateLimitCanBeTurnedOff(): void
    {
        $container = $this->load([
            'security' => [
                'reset_password' => [
                    'enabled' => true,
                    'rate_limit' => [
                        'enabled' => false,
                    ],
                ],
            ],
        ]);

        self::assertArrayNotHasKey('$limiter', $container->getDefinition(RequestResetPassword::class)->getArguments());
    }

    /**
     * The bundle, the limiter and the entity mapping are configured from the platform section, so
     * an application only has to switch the feature on.
     */
    public function testEnablingResetPasswordConfiguresTheBundleTheLimiterAndTheMapping(): void
    {
        $config = [
            'security' => [
                'reset_password' => [
                    'enabled' => true,
                    'lifetime' => 1800,
                    'throttle_limit' => 600,
                ],
            ],
        ];

        $container = $this->prepend($config, ['symfonycasts_reset_password', 'doctrine', 'framework']);

        self::assertSame([[
            'request_password_repository' => ResetPasswordRequestRepository::class,
            'lifetime' => 1800,
            'throttle_limit' => 600,
            'enable_garbage_collection' => true,
        ]], $container->getExtensionConfig('symfonycasts_reset_password'));

        self::assertSame([[
            'rate_limiter' => [
                'solidworx_platform_reset_password' => [
                    'policy' => 'sliding_window',
                    'limit' => 5,
                    'interval' => '15 minutes',
                ],
            ],
        ]], $container->getExtensionConfig('framework'));

        /** @var array{orm: array{mappings: array<string, array<string, mixed>>}} $doctrine */
        $doctrine = $container->getExtensionConfig('doctrine')[0];
        self::assertArrayHasKey('SolidWorxPlatformResetPassword', $doctrine['orm']['mappings']);
    }

    public function testWithResetPasswordDisabledTheEntityIsNotMapped(): void
    {
        $container = $this->prepend([], ['doctrine', 'framework']);

        /** @var array{orm: array{mappings: array<string, array<string, mixed>>}} $doctrine */
        $doctrine = $container->getExtensionConfig('doctrine')[0];
        self::assertArrayNotHasKey('SolidWorxPlatformResetPassword', $doctrine['orm']['mappings']);
        self::assertSame([], $container->getExtensionConfig('framework'));
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $extensions
     */
    private function prepend(array $config, array $extensions): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles_metadata', []);

        foreach ($extensions as $alias) {
            $container->registerExtension(new class($alias) extends Extension {
                public function __construct(
                    private readonly string $alias,
                ) {
                }

                public function load(array $configs, ContainerBuilder $container): void
                {
                }

                public function getAlias(): string
                {
                    return $this->alias;
                }
            });
        }

        new SolidWorxPlatformExtension($config)->prepend($container);

        return $container;
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
