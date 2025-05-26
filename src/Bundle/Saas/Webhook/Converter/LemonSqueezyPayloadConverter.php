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

namespace SolidWorx\Platform\SaasBundle\Webhook\Converter;

use SolidWorx\Platform\SaasBundle\Dto\LemonSqueezy\Subscription;
use SolidWorx\Platform\SaasBundle\Dto\LemonSqueezy\SubscriptionInvoice;
use SolidWorx\Platform\SaasBundle\Enum\LemonSqueezy\Event;
use SolidWorx\Platform\SaasBundle\RemoteEvent\SubscriptionPaymentRemoteEvent;
use SolidWorx\Platform\SaasBundle\RemoteEvent\SubscriptionRemoteEvent;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Uid\Ulid;

final readonly class LemonSqueezyPayloadConverter implements PayloadConverterInterface
{
    private DenormalizerInterface $serializer;

    public function __construct()
    {
        $this->serializer = $this->getSerializer();
    }

    /**
     * @param array{
     *     data: array<string, mixed>,
     *     meta: array{
     *         event_name: string,
     *         id: string,
     *         custom_data?: array{subscription_id: string},
     *     }
     * } $payload
     *
     * @throws ExceptionInterface
     */
    public function convert(array $payload): RemoteEvent
    {
        $type = $this->getMappingClass($payload['data']['type']);

        if ($type === null) {
            return new RemoteEvent(
                $payload['meta']['event_name'],
                $payload['meta']['id'],
                $payload['data']
            );
        }

        if (! isset($payload['meta']['custom_data']['subscription_id'])) {
            throw new ParseException('Payload does not contain required custom_data.subscription_id field.');
        }

        $data = $this->serializer->denormalize($payload['data'], $type, 'json');

        return match ($type) {
            Subscription::class => new SubscriptionRemoteEvent(
                Ulid::fromString($payload['meta']['custom_data']['subscription_id']),
                $data,
                Event::from($payload['meta']['event_name']),
                $payload,
            ),
            SubscriptionInvoice::class => new SubscriptionPaymentRemoteEvent(
                Ulid::fromString($payload['meta']['custom_data']['subscription_id']),
                $data,
                Event::from($payload['meta']['event_name']),
                $payload,
            ),
        };
    }

    /**
     * @return class-string<Subscription|SubscriptionInvoice>|null
     */
    private function getMappingClass(string $eventType): ?string
    {
        return match ($eventType) {
            'subscriptions' => Subscription::class,
            'subscription-invoices' => SubscriptionInvoice::class,
            default => null,
        };
    }

    private function getSerializer(): DenormalizerInterface
    {
        return new Serializer(
            normalizers: [
                new BackedEnumNormalizer(),
                new DateTimeNormalizer([
                    DateTimeNormalizer::TIMEZONE_KEY => 'UTC',
                    DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s.u\Z',
                ]),
                new ObjectNormalizer(
                    classMetadataFactory: new ClassMetadataFactory(new AttributeLoader()),
                    nameConverter: new CamelCaseToSnakeCaseNameConverter(),
                    propertyTypeExtractor: new PropertyInfoExtractor(
                        typeExtractors: [new ReflectionExtractor()]
                    ),
                ),
            ],
        );
    }
}
