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

namespace SolidWorx\Platform\Tests\Bundle\Saas\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Integration\LemonSqueezy;
use SolidWorx\Platform\SaasBundle\Integration\LemonSqueezyAffiliate;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(LemonSqueezy::class)]
final class LemonSqueezyCheckoutTest extends TestCase
{
    private const string CHECKOUT_URL = 'https://store.lemonsqueezy.com/checkout/custom/abc?expires=1&signature=sig';

    /**
     * @return iterable<string, array{array<string, string>, ?string, string}>
     */
    public static function cookieProvider(): iterable
    {
        yield 'no cookie' => [[], null, self::CHECKOUT_URL];
        yield 'stored ref' => [[], 'stored', self::CHECKOUT_URL . '&aff_ref=stored'];
        yield 'valid ref' => [[
            LemonSqueezyAffiliate::COOKIE => 'Ab_12-x',
        ], 'stored', self::CHECKOUT_URL . '&aff_ref=Ab_12-x'];
        yield 'tampered ref' => [[
            LemonSqueezyAffiliate::COOKIE => 'x&signature=evil',
        ], null, self::CHECKOUT_URL];
    }

    /**
     * @param array<string, string> $cookies
     */
    #[DataProvider('cookieProvider')]
    public function testCheckoutAppendsAffiliateRef(array $cookies, ?string $storedRef, string $expected): void
    {
        $requestStack = new RequestStack([new Request(cookies: $cookies)]);

        $integration = new LemonSqueezy(
            'key',
            '1',
            'return',
            self::createStub(UrlGeneratorInterface::class),
            $requestStack,
            new MockHttpClient(new JsonMockResponse([
                'data' => [
                    'attributes' => [
                        'url' => self::CHECKOUT_URL,
                    ],
                ],
            ])),
        );

        $subscription = new Subscription()->setPlan(new Plan()->setPlanId('123'))->setAffiliateRef($storedRef);

        $this->assertSame($expected, $integration->checkout($subscription));
    }
}
