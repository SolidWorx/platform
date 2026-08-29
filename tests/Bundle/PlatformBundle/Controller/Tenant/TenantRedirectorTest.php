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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Controller\Tenant;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Controller\Tenant\TenantRedirector;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantSessionStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(TenantRedirector::class)]
#[UsesClass(TenantSessionStorage::class)]
final class TenantRedirectorTest extends TestCase
{
    private TenantSessionStorage $sessionStorage;

    protected function setUp(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $this->sessionStorage = new TenantSessionStorage($requestStack, '_tenant_id');
    }

    public function testReturnsTheUserToThePageTheyWereHeadedFor(): void
    {
        $this->sessionStorage->setTargetPath('/invoices/123');

        $this->assertSame('/invoices/123', $this->createRedirector()->redirectAfterSelection()->getTargetUrl());
    }

    public function testFallsBackToTheConfiguredRoute(): void
    {
        $this->assertSame(
            '/generated/app_dashboard',
            $this->createRedirector('app_dashboard')->redirectAfterSelection()->getTargetUrl(),
        );
    }

    public function testFallsBackToTheSiteRoot(): void
    {
        $this->assertSame('/', $this->createRedirector()->redirectAfterSelection()->getTargetUrl());
    }

    /**
     * A remembered target is single-use, so returning to the selection page later does not replay
     * a destination the user has since moved on from.
     */
    public function testTheRememberedTargetIsUsedOnlyOnce(): void
    {
        $this->sessionStorage->setTargetPath('/invoices/123');

        $redirector = $this->createRedirector();
        $redirector->redirectAfterSelection();

        $this->assertSame('/', $redirector->redirectAfterSelection()->getTargetUrl());
    }

    private function createRedirector(?string $defaultRoute = null): TenantRedirector
    {
        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route): string => '/generated/' . $route,
        );

        return new TenantRedirector($this->sessionStorage, $urlGenerator, $defaultRoute);
    }
}
