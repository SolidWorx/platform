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

namespace SolidWorx\Platform\Tests\Bundle\Saas\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\SaasBundle\EventSubscriber\AffiliateReferralSubscriber;
use SolidWorx\Platform\SaasBundle\Integration\LemonSqueezyAffiliate;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(AffiliateReferralSubscriber::class)]
final class AffiliateReferralSubscriberTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, string>, int, ?string}>
     */
    public static function requestProvider(): iterable
    {
        yield 'valid ref' => [[
            'aff_ref' => 'click_1',
        ], HttpKernelInterface::MAIN_REQUEST, 'click_1'];
        yield 'invalid ref' => [[
            'aff_ref' => 'a b',
        ], HttpKernelInterface::MAIN_REQUEST, null];
        yield 'no ref' => [[], HttpKernelInterface::MAIN_REQUEST, null];
        yield 'sub request' => [[
            'aff_ref' => 'click_1',
        ], HttpKernelInterface::SUB_REQUEST, null];
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('requestProvider')]
    public function testStoresAffiliateRefCookie(array $query, int $requestType, ?string $expected): void
    {
        $response = new Response();

        new AffiliateReferralSubscriber()->onKernelResponse(new ResponseEvent(
            self::createStub(HttpKernelInterface::class),
            new Request($query),
            $requestType,
            $response,
        ));

        $cookies = array_filter(
            $response->headers->getCookies(),
            static fn (Cookie $cookie): bool => $cookie->getName() === LemonSqueezyAffiliate::COOKIE,
        );

        $this->assertSame($expected, $cookies === [] ? null : array_values($cookies)[0]->getValue());
    }
}
