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

namespace Bundle\Saas\Webhook\Converter;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\SaasBundle\RemoteEvent\SubscriptionPaymentRemoteEvent;
use SolidWorx\Platform\SaasBundle\RemoteEvent\SubscriptionRemoteEvent;
use SolidWorx\Platform\SaasBundle\Webhook\Converter\LemonSqueezyPayloadConverter;
use SolidWorx\Platform\Test\Traits\UsesFixturesTrait;
use Spatie\Snapshots\MatchesSnapshots;

#[CoversClass(LemonSqueezyPayloadConverter::class)]
final class LemonSqueezyPayloadConverterTest extends TestCase
{
    use UsesFixturesTrait;
    use MatchesSnapshots;

    /**
     * @param array<string, mixed> $payload
     * @param class-string<SubscriptionRemoteEvent|SubscriptionPaymentRemoteEvent> $expectedClass
     */
    #[DataProvider('provideConvert')]
    public function testConvert(array $payload, string $expectedClass): void
    {
        $converter = new LemonSqueezyPayloadConverter();

        $event = $converter->convert($payload);

        self::assertInstanceOf($expectedClass, $event);
        $this->assertMatchesObjectSnapshot($event);
    }

    /**
     * @return iterable<array{0: array<string, mixed>, 1: class-string<SubscriptionRemoteEvent|SubscriptionPaymentRemoteEvent>}>
     *
     * @throws JsonException
     */
    public static function provideConvert(): iterable
    {
        yield 'subscription created' => [
            self::loadFixture('webhook/lemon_squeezy/subscription_created.json'),
            SubscriptionRemoteEvent::class,
        ];

        yield 'subscription updated' => [
            self::loadFixture('webhook/lemon_squeezy/subscription_updated.json'),
            SubscriptionRemoteEvent::class,
        ];

        yield 'subscription updated on_trial' => [
            self::loadFixture('webhook/lemon_squeezy/subscription_updated.json'),
            SubscriptionRemoteEvent::class,
        ];

        yield 'subscription payment success' => [
            self::loadFixture('webhook/lemon_squeezy/subscription_payment_success.json'),
            SubscriptionPaymentRemoteEvent::class,
        ];
    }
}
