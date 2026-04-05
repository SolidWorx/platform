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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Config\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Config\Builder\PlatformConfigBuilder;
use SolidWorx\Platform\PlatformBundle\Config\Builder\SecurityConfigBuilder;

#[CoversClass(PlatformConfigBuilder::class)]
final class PlatformConfigBuilderTest extends TestCase
{
    public function testBuildAlwaysWrapsUnderPlatformKey(): void
    {
        $result = PlatformConfigBuilder::create()->build();

        self::assertArrayHasKey('platform', $result);
    }

    public function testDefaultNameAndVersion(): void
    {
        $result = PlatformConfigBuilder::create()->build();

        self::assertSame('SolidWorx Platform', $result['platform']['name']);
        self::assertSame('1.0.0', $result['platform']['version']);
    }

    public function testNameAndVersionCanBeOverridden(): void
    {
        $result = PlatformConfigBuilder::create()
            ->name('My App')
            ->version('2.5.0')
            ->build();

        self::assertSame('My App', $result['platform']['name']);
        self::assertSame('2.5.0', $result['platform']['version']);
    }

    public function testUserModelAppearsInBuild(): void
    {
        $result = PlatformConfigBuilder::create()
            ->userModel('App\Entity\User')
            ->build();

        self::assertSame('App\Entity\User', $result['platform']['models']['user']);
    }

    public function testModelsAbsentWhenNotSet(): void
    {
        $result = PlatformConfigBuilder::create()->build();
        self::assertArrayNotHasKey('models', $result['platform']);
    }

    public function testEnableUtcDateAppearsInBuild(): void
    {
        $result = PlatformConfigBuilder::create()
            ->enableUtcDate(true)
            ->build();

        self::assertTrue($result['platform']['doctrine']['types']['enable_utc_date']);
    }

    public function testDoctrineAbsentWhenUtcDateNotSet(): void
    {
        $result = PlatformConfigBuilder::create()->build();
        self::assertArrayNotHasKey('doctrine', $result['platform']);
    }

    public function testWithSaasConfigInjectsUnderSaasKey(): void
    {
        $saas = [
            'doctrine' => [
                'subscriptions' => [
                    'entity' => 'App\Entity\Subscription',
                ],
            ],
        ];
        $result = PlatformConfigBuilder::create()->withSaasConfig($saas)->build();

        self::assertSame($saas, $result['platform']['saas']);
    }

    public function testSaasAbsentWhenNotSet(): void
    {
        $result = PlatformConfigBuilder::create()->build();
        self::assertArrayNotHasKey('saas', $result['platform']);
    }

    public function testWithUiConfigInjectsUnderUiKey(): void
    {
        $ui = [
            'icon_pack' => 'tabler',
        ];
        $result = PlatformConfigBuilder::create()->withUiConfig($ui)->build();

        self::assertSame($ui, $result['platform']['ui']);
    }

    public function testUiAbsentWhenNotSet(): void
    {
        $result = PlatformConfigBuilder::create()->build();
        self::assertArrayNotHasKey('ui', $result['platform']);
    }

    public function testSecurityBuilderChainReturnsParent(): void
    {
        $builder = PlatformConfigBuilder::create();
        $securityBuilder = $builder->security();

        self::assertInstanceOf(SecurityConfigBuilder::class, $securityBuilder);
        self::assertSame($builder, $securityBuilder->end());
    }
}
