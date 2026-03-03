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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Enum\Flash;
use SolidWorx\Platform\PlatformBundle\EventSubscriber\FlashResponseSubscriber;
use SolidWorx\Platform\PlatformBundle\Response\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(FlashResponseSubscriber::class)]
final class FlashResponseSubscriberTest extends TestCase
{
    public function testSubscribesToResponseEvent(): void
    {
        $events = FlashResponseSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(ResponseEvent::class, $events);
        self::assertSame('onKernelResponse', $events[ResponseEvent::class]);
    }

    public function testIgnoresSubRequests(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/');
        $session = new Session(new MockArraySessionStorage(), null, new FlashBag());
        $request->setSession($session);

        $response = new RedirectResponse('/target');
        $response->withFlash(Flash::Success, 'Should not appear');

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $subscriber = new FlashResponseSubscriber();
        $subscriber->onKernelResponse($event);

        self::assertSame([], $session->getFlashBag()->all());
    }

    public function testIgnoresNonRedirectResponse(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/');
        $session = new Session(new MockArraySessionStorage(), null, new FlashBag());
        $request->setSession($session);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response('OK'));

        $subscriber = new FlashResponseSubscriber();
        $subscriber->onKernelResponse($event);

        self::assertSame([], $session->getFlashBag()->all());
    }

    public function testTransfersFlashesToFlashBag(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/');
        $session = new Session(new MockArraySessionStorage(), null, new FlashBag());
        $request->setSession($session);

        $response = new RedirectResponse('/target');
        $response->withFlash(Flash::Success, 'Saved successfully');
        $response->withFlash(Flash::Error, 'Something went wrong');

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber = new FlashResponseSubscriber();
        $subscriber->onKernelResponse($event);

        self::assertSame(['Saved successfully'], $session->getFlashBag()->get('success'));
        self::assertSame(['Something went wrong'], $session->getFlashBag()->get('error'));
    }

    public function testNoOpWhenResponseHasNoFlashes(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/');
        $session = new Session(new MockArraySessionStorage(), null, new FlashBag());
        $request->setSession($session);

        $response = new RedirectResponse('/target');

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber = new FlashResponseSubscriber();
        $subscriber->onKernelResponse($event);

        self::assertSame([], $session->getFlashBag()->all());
    }
}
