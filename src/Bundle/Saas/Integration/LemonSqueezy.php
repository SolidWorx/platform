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

namespace SolidWorx\Platform\SaasBundle\Integration;

use Carbon\CarbonInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use SolidWorx\Platform\SaasBundle\Dto\IntegrationProduct;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Exception\PaymentIntegrationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LemonSqueezy implements PaymentIntegrationInterface
{
    private const string BASE_URI = 'https://api.lemonsqueezy.com/v1/';

    private readonly HttpClientInterface $httpClient;

    public function __construct(
        string $apiKey,
        private readonly string $storeId,
        #[Autowire(param: 'solidworx_platform.saas.payment.return_route')]
        private readonly string $returnRoute,
        private readonly UrlGeneratorInterface $router,
    ) {
        $this->httpClient = HttpClient::createForBaseUri(
            self::BASE_URI,
            [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $apiKey),
                    'Accept' => 'application/vnd.api+json',
                    'Content-Type' => 'application/vnd.api+json',
                ],
            ]
        );
    }

    #[Override]
    public function getPlans(): iterable
    {
        $response = $this->httpClient->request(
            Request::METHOD_GET,
            'products',
            [
                'query' => [
                    'filter[store_id]' => $this->storeId,
                ],
            ]
        );

        $data = $response->toArray();
        $products = $data['data'] ?? null;

        if (! is_array($products)) {
            return;
        }

        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            $attributes = $this->arrayValue($product, 'attributes');

            $relationships = $this->optionalArrayValue($product, 'relationships');
            $relatedData = $this->relatedLink($relationships, 'variants');

            if ($relatedData === null) {
                continue;
            }

            $variants = $this->httpClient->request(
                Request::METHOD_GET,
                $relatedData,
            );

            $variantsData = $variants->toArray();
            $variantItems = $variantsData['data'] ?? null;

            if (! is_array($variantItems)) {
                continue;
            }

            foreach ($variantItems as $variant) {
                if (! is_array($variant)) {
                    continue;
                }

                $variantRelationships = $this->optionalArrayValue($variant, 'relationships');
                $priceModel = $this->relatedLink($variantRelationships, 'price-model');

                if ($priceModel === null) {
                    continue;
                }

                $priceModelResponse = $this->httpClient->request(
                    Request::METHOD_GET,
                    $priceModel,
                );

                $priceModelData = $priceModelResponse->toArray();
                $priceModelAttributes = $this->arrayValue(
                    $this->arrayValue($priceModelData, 'data'),
                    'attributes',
                );

                $interval = CarbonInterval::fromString(
                    sprintf(
                        '%s %s',
                        $this->intValue($priceModelAttributes, 'renewal_interval_quantity'),
                        $this->stringValue($priceModelAttributes, 'renewal_interval_unit'),
                    )
                );

                yield new IntegrationProduct(
                    id: $this->stringValue($variant, 'id'),
                    name: $this->stringValue($attributes, 'name'),
                    description: $this->stringValue($attributes, 'description'),
                    price: $this->intValue($priceModelAttributes, 'unit_price'),
                    interval: $interval,
                );
            }
        }
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    #[Override]
    public function checkout(Subscription $subscription, ?Options $options = null): string
    {
        $response = $this->httpClient->request(
            Request::METHOD_POST,
            'checkouts',
            [
                'json' => [
                    'data' => [
                        'type' => 'checkouts',
                        'attributes' => [
                            'product_options' => [
                                'redirect_url' => $this->router->generate($this->returnRoute, referenceType: UrlGeneratorInterface::ABSOLUTE_URL),
                            ],
                            'checkout_data' => [
                                'email' => $options?->getValue(Options::EMAIL),
                                'custom' => [
                                    'subscription_id' => $subscription->getId()->toBase58(),
                                ],
                            ],
                            'checkout_options' => [
                                'skip_trial' => $options?->getValue(Options::SKIP_TRIAL) ?? false,
                            ],
                        ],
                        'relationships' => [
                            'store' => [
                                'data' => [
                                    'type' => 'stores',
                                    'id' => $this->storeId,
                                ],
                            ],
                            'variant' => [
                                'data' => [
                                    'type' => 'variants',
                                    'id' => $subscription->getPlan()->getPlanId(),
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $data = $response->toArray();
        $attributes = $this->arrayValue($this->arrayValue($data, 'data'), 'attributes');

        return $this->stringValue($attributes, 'url');
    }

    #[Override]
    public function getCustomerPortalUrl(Subscription $subscription): string
    {
        $subscriptionId = $subscription->getSubscriptionId();

        if ($subscriptionId === null) {
            throw new InvalidArgumentException('Subscription ID is not set.');
        }

        $response = $this->httpClient->request(
            Request::METHOD_GET,
            'subscriptions/' . $subscriptionId,
        );

        $data = $response->toArray();
        $urls = $this->arrayValue(
            $this->arrayValue($this->arrayValue($data, 'data'), 'attributes'),
            'urls',
        );

        return $this->stringValue($urls, 'customer_portal');
    }

    #[Override]
    public function changePlan(Subscription $subscription, Plan $newPlan): DateTimeImmutable
    {
        $subscriptionId = $this->requireSubscriptionId($subscription);

        $response = $this->httpClient->request(
            Request::METHOD_PATCH,
            'subscriptions/' . $subscriptionId,
            [
                'json' => [
                    'data' => [
                        'type' => 'subscriptions',
                        'id' => $subscriptionId,
                        'attributes' => [
                            'variant_id' => (int) $newPlan->getPlanId(),
                            'invoice_immediately' => true,
                            'disable_prorations' => false,
                        ],
                    ],
                ],
            ]
        );

        $this->assertOk($response->getStatusCode(), 'change plan', $subscriptionId);

        return $this->extractRenewDate($response->toArray(), $subscriptionId);
    }

    #[Override]
    public function cancelAtPeriodEnd(Subscription $subscription): DateTimeImmutable
    {
        $subscriptionId = $this->requireSubscriptionId($subscription);

        $response = $this->httpClient->request(
            Request::METHOD_DELETE,
            'subscriptions/' . $subscriptionId,
        );

        $this->assertOk($response->getStatusCode(), 'cancel', $subscriptionId);

        $data = $response->toArray();
        $dataNode = $data['data'] ?? null;
        $attributes = is_array($dataNode) ? ($dataNode['attributes'] ?? null) : null;
        $endsAt = is_array($attributes) ? ($attributes['ends_at'] ?? null) : null;

        if (! is_string($endsAt) || $endsAt === '') {
            throw new PaymentIntegrationException(sprintf(
                'Lemon Squeezy did not return an ends_at date for cancellation of subscription "%s".',
                $subscriptionId,
            ));
        }

        return new DateTimeImmutable($endsAt);
    }

    #[Override]
    public function resume(Subscription $subscription): DateTimeImmutable
    {
        $subscriptionId = $this->requireSubscriptionId($subscription);

        $response = $this->httpClient->request(
            Request::METHOD_PATCH,
            'subscriptions/' . $subscriptionId,
            [
                'json' => [
                    'data' => [
                        'type' => 'subscriptions',
                        'id' => $subscriptionId,
                        'attributes' => [
                            'cancelled' => false,
                        ],
                    ],
                ],
            ]
        );

        $this->assertOk($response->getStatusCode(), 'resume', $subscriptionId);

        return $this->extractRenewDate($response->toArray(), $subscriptionId);
    }

    private function requireSubscriptionId(Subscription $subscription): string
    {
        $subscriptionId = $subscription->getSubscriptionId();

        if ($subscriptionId === null || $subscriptionId === '') {
            throw new InvalidArgumentException(sprintf(
                'Subscription "%s" has no external Lemon Squeezy id.',
                $subscription->getId()->toBase58(),
            ));
        }

        return $subscriptionId;
    }

    private function assertOk(int $statusCode, string $action, string $subscriptionId): void
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        throw new PaymentIntegrationException(sprintf(
            'Lemon Squeezy %s failed for subscription "%s" (HTTP %d).',
            $action,
            $subscriptionId,
            $statusCode,
        ));
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function arrayValue(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            throw new PaymentIntegrationException(sprintf('Lemon Squeezy response is missing array key "%s".', $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function optionalArrayValue(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function stringValue(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value)) {
            throw new PaymentIntegrationException(sprintf('Lemon Squeezy response is missing string key "%s".', $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function intValue(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (! is_int($value)) {
            throw new PaymentIntegrationException(sprintf('Lemon Squeezy response is missing integer key "%s".', $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $relationships
     */
    private function relatedLink(array $relationships, string $relationship): ?string
    {
        $node = $relationships[$relationship] ?? null;

        if (! is_array($node)) {
            return null;
        }

        $links = $node['links'] ?? null;

        if (! is_array($links)) {
            return null;
        }

        $related = $links['related'] ?? null;

        return is_string($related) ? $related : null;
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function extractRenewDate(array $payload, string $subscriptionId): DateTimeImmutable
    {
        $data = $payload['data'] ?? null;
        $attributes = is_array($data) ? ($data['attributes'] ?? null) : null;
        $renewsAt = is_array($attributes) ? ($attributes['renews_at'] ?? $attributes['ends_at'] ?? null) : null;

        if (! is_string($renewsAt) || $renewsAt === '') {
            throw new PaymentIntegrationException(sprintf(
                'Lemon Squeezy did not return a renews_at/ends_at date for subscription "%s".',
                $subscriptionId,
            ));
        }

        return new DateTimeImmutable($renewsAt);
    }
}
