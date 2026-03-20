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

namespace SolidWorx\Platform\Tests\Bundle\Saas\Config;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\SaasBundle\Config\SaasConfiguration;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Entity\PlanFeature;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Entity\SubscriptionLog;
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(SaasConfiguration::class)]
final class SaasConfigurationTest extends TestCase
{
    private SaasConfiguration $configuration;

    private Processor $processor;

    /**
     * @var class-string
     */
    private string $validEntityClass;

    #[Override]
    protected function setUp(): void
    {
        $this->configuration = new SaasConfiguration();
        $this->processor = new Processor();

        // Create a minimal entity class implementing SubscribableInterface
        $entity = new class() implements SubscribableInterface {};
        $this->validEntityClass = $entity::class;
    }

    public function testGetConfigSectionKeyReturnsSaas(): void
    {
        self::assertSame('saas', $this->configuration->getConfigSectionKey());
    }

    public function testTreeBuilderRootNodeIsNamedSaas(): void
    {
        $tree = $this->configuration->getTreeBuilder()->buildTree();
        self::assertSame('saas', $tree->getName());
    }

    public function testTreeBuilderRootNodeIsArrayNode(): void
    {
        $tree = $this->configuration->getTreeBuilder()->buildTree();
        self::assertInstanceOf(ArrayNode::class, $tree);
    }

    public function testGetTreeBuilderReturnsFreshInstanceEachCall(): void
    {
        self::assertNotSame(
            $this->configuration->getTreeBuilder(),
            $this->configuration->getTreeBuilder(),
        );
    }

    public function testDoctrineSubscriptionsEntityIsRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([
            'payment' => [
                'return_route' => 'app_success',
            ],
        ]);
    }

    public function testSubscriptionEntityMustImplementSubscribableInterface(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([
            'doctrine' => [
                'subscriptions' => [
                    'entity' => \stdClass::class,
                ],
            ],
            'payment' => [
                'return_route' => 'app_success',
            ],
        ]);
    }

    public function testSubscriptionEntityCannotBeEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([
            'doctrine' => [
                'subscriptions' => [
                    'entity' => '',
                ],
            ],
            'payment' => [
                'return_route' => 'app_success',
            ],
        ]);
    }

    public function testPaymentReturnRouteIsRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([
            'doctrine' => [
                'subscriptions' => [
                    'entity' => $this->validEntityClass,
                ],
            ],
        ]);
    }

    public function testMinimalValidConfigProcesses(): void
    {
        $result = $this->process($this->minimalConfig());
        self::assertSame($this->validEntityClass, $result['doctrine']['subscriptions']['entity']);
        self::assertSame('app_payment_success', $result['payment']['return_route']);
    }

    public function testDefaultTableNameForPlan(): void
    {
        $result = $this->process($this->minimalConfig());
        self::assertSame(Plan::TABLE_NAME, $result['doctrine']['db_schema']['table_names']['plan']);
    }

    public function testDefaultTableNameForSubscription(): void
    {
        $result = $this->process($this->minimalConfig());
        self::assertSame(Subscription::TABLE_NAME, $result['doctrine']['db_schema']['table_names']['subscription']);
    }

    public function testDefaultTableNameForSubscriptionLog(): void
    {
        $result = $this->process($this->minimalConfig());
        self::assertSame(SubscriptionLog::TABLE_NAME, $result['doctrine']['db_schema']['table_names']['subscription_log']);
    }

    public function testDefaultTableNameForPlanFeature(): void
    {
        $result = $this->process($this->minimalConfig());
        self::assertSame(PlanFeature::TABLE_NAME, $result['doctrine']['db_schema']['table_names']['plan_feature']);
    }

    public function testCustomTableNamesAreApplied(): void
    {
        $config = $this->minimalConfig();
        $config['doctrine']['db_schema'] = [
            'table_names' => [
                'plan' => 'custom_plans',
                'subscription' => 'custom_subs',
                'subscription_log' => 'custom_sub_logs',
                'plan_feature' => 'custom_features',
            ],
        ];

        $result = $this->process($config);

        self::assertSame('custom_plans', $result['doctrine']['db_schema']['table_names']['plan']);
        self::assertSame('custom_subs', $result['doctrine']['db_schema']['table_names']['subscription']);
        self::assertSame('custom_sub_logs', $result['doctrine']['db_schema']['table_names']['subscription_log']);
        self::assertSame('custom_features', $result['doctrine']['db_schema']['table_names']['plan_feature']);
    }

    public function testInvalidTableNameIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $config = $this->minimalConfig();
        $config['doctrine']['db_schema'] = [
            'table_names' => [
                'plan' => '1invalid',
            ],
        ];
        $this->process($config);
    }

    public function testLemonSqueezyIsDisabledByDefault(): void
    {
        $result = $this->process($this->minimalConfig());
        self::assertFalse($result['integration']['lemon_squeezy']['enabled']);
    }

    public function testLemonSqueezyCanBeEnabled(): void
    {
        $config = $this->minimalConfig();
        $config['integration'] = [
            'lemon_squeezy' => [
                'enabled' => true,
                'api_key' => 'key_test',
                'webhook_secret' => 'whsec_test',
                'store_id' => 'store_123',
            ],
        ];

        $result = $this->process($config);

        self::assertTrue($result['integration']['lemon_squeezy']['enabled']);
        self::assertSame('key_test', $result['integration']['lemon_squeezy']['api_key']);
        self::assertSame('whsec_test', $result['integration']['lemon_squeezy']['webhook_secret']);
        self::assertSame('store_123', $result['integration']['lemon_squeezy']['store_id']);
    }

    public function testFeaturesDefaultToEmptyArray(): void
    {
        $result = $this->process($this->minimalConfig());
        self::assertSame([], $result['features']);
    }

    public function testBooleanFeatureDefinition(): void
    {
        $config = $this->minimalConfig();
        $config['features'] = [
            'file_uploads' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Allow uploads',
            ],
        ];

        $result = $this->process($config);

        self::assertSame('boolean', $result['features']['file_uploads']['type']);
        self::assertFalse($result['features']['file_uploads']['default']);
        self::assertSame('Allow uploads', $result['features']['file_uploads']['description']);
    }

    public function testIntegerFeatureDefinition(): void
    {
        $config = $this->minimalConfig();
        $config['features'] = [
            'api_calls' => [
                'type' => 'integer',
                'default' => 1000,
            ],
        ];

        $result = $this->process($config);

        self::assertSame('integer', $result['features']['api_calls']['type']);
        self::assertSame(1000, $result['features']['api_calls']['default']);
        self::assertSame('', $result['features']['api_calls']['description']);
    }

    public function testInvalidFeatureTypeIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $config = $this->minimalConfig();
        $config['features'] = [
            'bad_feature' => [
                'type' => 'float',
                'default' => 1.5,
            ],
        ];
        $this->process($config);
    }

    public function testMultipleFeaturesCanBeDefined(): void
    {
        $config = $this->minimalConfig();
        $config['features'] = [
            'api_calls' => [
                'type' => 'integer',
                'default' => 500,
            ],
            'reports' => [
                'type' => 'boolean',
                'default' => false,
            ],
            'theme' => [
                'type' => 'string',
                'default' => 'light',
            ],
        ];

        $result = $this->process($config);

        self::assertCount(3, $result['features']);
        self::assertArrayHasKey('api_calls', $result['features']);
        self::assertArrayHasKey('reports', $result['features']);
        self::assertArrayHasKey('theme', $result['features']);
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalConfig(): array
    {
        return [
            'doctrine' => [
                'subscriptions' => [
                    'entity' => $this->validEntityClass,
                ],
            ],
            'payment' => [
                'return_route' => 'app_payment_success',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{doctrine: array{subscriptions: array{entity: string}, db_schema: array{table_names: array{plan: string, subscription: string, subscription_log: string, plan_feature: string}}}, payment: array{return_route: string}, integration: array{lemon_squeezy: array{enabled: bool, api_key: string|null, webhook_secret: string|null, store_id: string|null}}, features: array<string, array{type: string, default: mixed, description: string}>}
     */
    private function process(array $config): array
    {
        /** @var array{doctrine: array{subscriptions: array{entity: string}, db_schema: array{table_names: array{plan: string, subscription: string, subscription_log: string, plan_feature: string}}}, payment: array{return_route: string}, integration: array{lemon_squeezy: array{enabled: bool, api_key: string|null, webhook_secret: string|null, store_id: string|null}}, features: array<string, array{type: string, default: mixed, description: string}>} */
        return $this->processor->process($this->configuration->getTreeBuilder()->buildTree(), [$config]);
    }
}
