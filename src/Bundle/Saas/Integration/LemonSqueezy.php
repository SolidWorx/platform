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
use Override;
use SolidWorx\Platform\SaasBundle\Dto\IntegrationProduct;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
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

        foreach ($data['data'] as $product) {
            $attributes = $product['attributes'];

            $relatedData = $product['relationships']['variants']['links']['related'] ?? null;

            if ($relatedData === null) {
                continue;
            }

            $variants = $this->httpClient->request(
                Request::METHOD_GET,
                $relatedData,
            );

            $variantsData = $variants->toArray();

            foreach ($variantsData['data'] as $variant) {
                $priceModel = $variant['relationships']['price-model']['links']['related'] ?? null;

                if ($priceModel === null) {
                    continue;
                }

                $priceModelResponse = $this->httpClient->request(
                    Request::METHOD_GET,
                    $priceModel,
                );

                $priceModelData = $priceModelResponse->toArray();

                $interval = CarbonInterval::fromString(
                    sprintf(
                        '%s %s',
                        $priceModelData['data']['attributes']['renewal_interval_quantity'],
                        $priceModelData['data']['attributes']['renewal_interval_unit'],
                    )
                );

                yield new IntegrationProduct(
                    id: $variant['id'],
                    name: $attributes['name'],
                    description: $attributes['description'],
                    price: $priceModelData['data']['attributes']['unit_price'],
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
    public function checkout(Subscription $subscription, array $additionalInfo = []): string
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
                                'email' => $additionalInfo['email'] ?? null,
                                'custom' => [
                                    'subscription_id' => $subscription->getId()->toBase58(),
                                ],
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

        return $data['data']['attributes']['url'];
    }

    public function getCustomerPortalUrl(Subscription $subscription): string
    {
        $subscriptionId = $subscription->getSubscriptionId();

        $response = $this->httpClient->request(
            Request::METHOD_GET,
            'subscriptions/' . $subscriptionId,
        );

        $data = $response->toArray();

        return $data['data']['attributes']['urls']['customer_portal'];
    }
}
