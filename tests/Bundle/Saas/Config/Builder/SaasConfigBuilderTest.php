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

        self::assertSame('App\Entity\Subscription', $this->arrayAt($result, 'doctrine', 'subscriptions')['entity']);
    }

    public function testTrialUserEntityAppearsInBuild(): void
    {
        $result = SaasConfigBuilder::create()
            ->trialUserEntity('App\Entity\User')
            ->build();

        self::assertSame('App\Entity\User', $this->arrayAt($result, 'doctrine', 'trial')['user_entity']);
    }

    public function testTableNameAppearsInBuild(): void
    {
        $result = SaasConfigBuilder::create()
            ->tableName('plan', 'my_plans')
            ->tableName('trial', 'my_trials')
            ->build();

        $tableNames = $this->arrayAt($result, 'doctrine', 'db_schema', 'table_names');
        self::assertSame('my_plans', $tableNames['plan']);
        self::assertSame('my_trials', $tableNames['trial']);
    }

    public function testTableNamesAbsentWhenNoneSet(): void
    {
        $result = SaasConfigBuilder::create()->build();
        $doctrine = $result['doctrine'] ?? [];
        self::assertIsArray($doctrine);
        self::assertArrayNotHasKey('db_schema', $doctrine);
    }

    public function testLemonSqueezyAppearsWhenSet(): void
    {
        $result = SaasConfigBuilder::create()
            ->lemonSqueezy('key_123', 'secret_abc', 'store_xyz')
            ->build();

        $lemonSqueezy = $this->arrayAt($result, 'integration', 'lemon_squeezy');
        self::assertTrue($lemonSqueezy['enabled']);
        self::assertSame('key_123', $lemonSqueezy['api_key']);
        self::assertSame('secret_abc', $lemonSqueezy['webhook_secret']);
        self::assertSame('store_xyz', $lemonSqueezy['store_id']);
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

        $features = $this->arrayAt($result, 'features');
        self::assertCount(2, $features);
        self::assertArrayHasKey('api_calls', $features);
        self::assertArrayHasKey('uploads', $features);
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

        self::assertSame('app_payment_success', $this->arrayAt($result, 'payment')['return_route']);
    }

    /**
     * Navigate a nested config array, asserting each intermediate step is an array.
     *
     * @param array<array-key, mixed> $config
     * @return array<array-key, mixed>
     */
    private function arrayAt(array $config, string ...$keys): array
    {
        $current = $config;

        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $current);
            $value = $current[$key];
            self::assertIsArray($value);
            $current = $value;
        }

        return $current;
    }
}
