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

namespace SolidWorx\Platform\Tests\Bundle\Saas\Config\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\SaasBundle\Config\Builder\SaasConfigBuilder;
use SolidWorx\Platform\SaasBundle\Config\Builder\SaasPaymentConfigBuilder;

#[CoversClass(SaasConfigBuilder::class)]
final class SaasConfigBuilderTest extends TestCase
{
    public function testBuildWithNoFieldsReturnsEmptyArray(): void
    {
        self::assertSame([], SaasConfigBuilder::create()->build());
    }

    public function testSubscriptionEntityAppearsInBuild(): void
    {
        $result = SaasConfigBuilder::create()
            ->subscriptionEntity('App\Entity\Subscription')
            ->build();

        self::assertSame('App\Entity\Subscription', $result['doctrine']['subscriptions']['entity']);
    }

    public function testTrialUserEntityAppearsInBuild(): void
    {
        $result = SaasConfigBuilder::create()
            ->trialUserEntity('App\Entity\User')
            ->build();

        self::assertSame('App\Entity\User', $result['doctrine']['trial']['user_entity']);
    }

    public function testTableNameAppearsInBuild(): void
    {
        $result = SaasConfigBuilder::create()
            ->tableName('plan', 'my_plans')
            ->tableName('trial', 'my_trials')
            ->build();

        self::assertSame('my_plans', $result['doctrine']['db_schema']['table_names']['plan']);
        self::assertSame('my_trials', $result['doctrine']['db_schema']['table_names']['trial']);
    }

    public function testTableNamesAbsentWhenNoneSet(): void
    {
        $result = SaasConfigBuilder::create()->build();
        self::assertArrayNotHasKey('db_schema', $result['doctrine'] ?? []);
    }

    public function testLemonSqueezyAppearsWhenSet(): void
    {
        $result = SaasConfigBuilder::create()
            ->lemonSqueezy('key_123', 'secret_abc', 'store_xyz')
            ->build();

        self::assertTrue($result['integration']['lemon_squeezy']['enabled']);
        self::assertSame('key_123', $result['integration']['lemon_squeezy']['api_key']);
        self::assertSame('secret_abc', $result['integration']['lemon_squeezy']['webhook_secret']);
        self::assertSame('store_xyz', $result['integration']['lemon_squeezy']['store_id']);
    }

    public function testFeaturesAccumulateAcrossCalls(): void
    {
        $result = SaasConfigBuilder::create()
            ->feature('api_calls', [
                'type' => 'integer',
                'default' => 500,
            ])
            ->feature('uploads', [
                'type' => 'boolean',
                'default' => false,
            ])
            ->build();

        self::assertCount(2, $result['features']);
        self::assertArrayHasKey('api_calls', $result['features']);
        self::assertArrayHasKey('uploads', $result['features']);
    }

    public function testPaymentBuilderChainReturnsParent(): void
    {
        $builder = SaasConfigBuilder::create();
        $paymentBuilder = $builder->payment();

        self::assertInstanceOf(SaasPaymentConfigBuilder::class, $paymentBuilder);
        self::assertSame($builder, $paymentBuilder->end());
    }

    public function testPaymentReturnRouteAppearsInBuild(): void
    {
        $result = SaasConfigBuilder::create()
            ->payment()
                ->returnRoute('app_payment_success')
            ->end()
            ->build();

        self::assertSame('app_payment_success', $result['payment']['return_route']);
    }
}
