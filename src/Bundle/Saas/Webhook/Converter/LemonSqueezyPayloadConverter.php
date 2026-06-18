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

use Override;
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
     * @param array<string, mixed> $payload
     *
     * @throws ExceptionInterface
     */
    #[Override]
    public function convert(array $payload): RemoteEvent
    {
        $data = $this->arrayValue($payload, 'data');
        $meta = $this->arrayValue($payload, 'meta');

        $type = $this->getMappingClass($this->stringValue($data, 'type'));

        if ($type === null) {
            return new RemoteEvent(
                $this->stringValue($meta, 'event_name'),
                $this->stringValue($meta, 'id'),
                $data,
            );
        }

        $customData = $this->arrayValue($meta, 'custom_data');
        $subscriptionId = $customData['subscription_id'] ?? null;

        if (! is_string($subscriptionId)) {
            throw new ParseException('Payload does not contain required custom_data.subscription_id field.');
        }

        $denormalized = $this->serializer->denormalize($data, $type, 'json');
        $eventName = $this->stringValue($meta, 'event_name');

        if ($denormalized instanceof Subscription) {
            return new SubscriptionRemoteEvent(
                Ulid::fromString($subscriptionId),
                $denormalized,
                Event::from($eventName),
                $payload,
            );
        }

        if ($denormalized instanceof SubscriptionInvoice) {
            return new SubscriptionPaymentRemoteEvent(
                Ulid::fromString($subscriptionId),
                $denormalized,
                Event::from($eventName),
                $payload,
            );
        }

        throw new ParseException(sprintf('Unsupported type: %s', $type));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function arrayValue(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            throw new ParseException(sprintf('Expected array at key "%s".', $key));
        }

        $result = [];

        foreach ($value as $childKey => $childValue) {
            $result[(string) $childKey] = $childValue;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function stringValue(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value)) {
            throw new ParseException(sprintf('Expected string at key "%s".', $key));
        }

        return $value;
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
