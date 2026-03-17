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

namespace SolidWorx\Platform\SaasBundle\Config\Builder;

/**
 * PHP fluent builder for the `platform.saas:` configuration section.
 *
 * Usage:
 *
 *     use SolidWorx\Platform\SaasBundle\Config\Builder\SaasConfigBuilder;
 *     use SolidWorx\Platform\PlatformBundle\Config\Builder\PlatformConfigBuilder;
 *
 *     return PlatformConfigBuilder::create()
 *         ->withSaasConfig(
 *             SaasConfigBuilder::create()
 *                 ->subscriptionEntity(App\Entity\Subscription::class)
 *                 ->payment()->returnRoute('app_payment_return')->end()
 *                 ->build()
 *         )
 *         ->build();
 */
final class SaasConfigBuilder
{
    private ?string $subscriptionEntity = null;

    /**
     * @var array<string, string>
     */
    private array $tableNames = [];

    private ?SaasPaymentConfigBuilder $payment = null;

    private bool $lemonSqueezyEnabled = false;

    private string $lemonSqueezyApiKey = '%env(LEMON_SQUEEZY_API_KEY)%';

    private string $lemonSqueezyWebhookSecret = '%env(LEMON_SQUEEZY_WEBHOOK_SECRET)%';

    private string $lemonSqueezyStoreId = '%env(LEMON_SQUEEZY_STORE_ID)%';

    /**
     * @var array<string, mixed>
     */
    private array $features = [];

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function subscriptionEntity(string $class): self
    {
        $this->subscriptionEntity = $class;
        return $this;
    }

    public function tableName(string $key, string $name): self
    {
        $this->tableNames[$key] = $name;
        return $this;
    }

    public function payment(): SaasPaymentConfigBuilder
    {
        $this->payment = SaasPaymentConfigBuilder::create($this);
        return $this->payment;
    }

    public function lemonSqueezy(string $apiKey, string $webhookSecret, string $storeId): self
    {
        $this->lemonSqueezyEnabled = true;
        $this->lemonSqueezyApiKey = $apiKey;
        $this->lemonSqueezyWebhookSecret = $webhookSecret;
        $this->lemonSqueezyStoreId = $storeId;
        return $this;
    }

    /**
     * @param array<string, mixed> $definition Keys: type, default, description
     */
    public function feature(string $name, array $definition): self
    {
        $this->features[$name] = $definition;
        return $this;
    }

    /**
     * Returns the raw saas config array — no validation at this stage.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $config = [];

        if ($this->subscriptionEntity !== null) {
            $config['doctrine']['subscriptions']['entity'] = $this->subscriptionEntity;
        }

        if ($this->tableNames !== []) {
            $config['doctrine']['db_schema']['table_names'] = $this->tableNames;
        }

        if ($this->payment instanceof SaasPaymentConfigBuilder) {
            $config['payment'] = $this->payment->toArray();
        }

        if ($this->lemonSqueezyEnabled) {
            $config['integration']['lemon_squeezy'] = [
                'enabled' => true,
                'api_key' => $this->lemonSqueezyApiKey,
                'webhook_secret' => $this->lemonSqueezyWebhookSecret,
                'store_id' => $this->lemonSqueezyStoreId,
            ];
        }

        if ($this->features !== []) {
            $config['features'] = $this->features;
        }

        return $config;
    }
}
