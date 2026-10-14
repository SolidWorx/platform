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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Security\EmailVerification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Security\EmailVerification\EmailVerificationBannerListener;
use SolidWorx\Platform\PlatformBundle\Security\EmailVerification\EmailVerificationGateInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(EmailVerificationBannerListener::class)]
final class EmailVerificationBannerListenerTest extends TestCase
{
    private const string PAGE = '<body><div class="page-wrapper"><main>content</main></div></body>';

    public function testPutsTheBannerAtTheTopOfThePage(): void
    {
        self::assertSame(
            '<body><div class="page-wrapper"><div id="banner"></div><main>content</main></div></body>',
            $this->dispatch(gated: true),
        );
    }

    public function testLeavesThePageAloneForAVerifiedUser(): void
    {
        self::assertSame(self::PAGE, $this->dispatch(gated: false));
    }

    public function testLeavesNonPageResponsesAlone(): void
    {
        self::assertSame(self::PAGE, $this->dispatch(gated: true, method: 'POST'));
        self::assertSame(self::PAGE, $this->dispatch(gated: true, status: Response::HTTP_FOUND));
        self::assertSame('{"json":true}', $this->dispatch(gated: true, content: '{"json":true}'));
    }

    private function dispatch(bool $gated, string $method = 'GET', int $status = Response::HTTP_OK, string $content = self::PAGE): string
    {
        $gate = self::createStub(EmailVerificationGateInterface::class);
        $gate->method('isGated')->willReturn($gated);

        $twig = new Environment(new ArrayLoader([
            'banner.html.twig' => '<div id="banner"></div>',
        ]));

        $response = new Response($content, $status);
        $event = new ResponseEvent(self::createStub(HttpKernelInterface::class), Request::create('/', $method), HttpKernelInterface::MAIN_REQUEST, $response);

        (new EmailVerificationBannerListener($gate, $twig, 'banner.html.twig'))($event);

        return (string) $response->getContent();
    }
}
