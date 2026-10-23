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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Security;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Exception\UntrustedLinkHostException;
use SolidWorx\Platform\PlatformBundle\Security\EmailLinkContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

#[CoversClass(EmailLinkContext::class)]
#[CoversClass(UntrustedLinkHostException::class)]
final class EmailLinkContextTest extends TestCase
{
    private Router $router;

    private RequestStack $requestStack;

    #[Override]
    protected function setUp(): void
    {
        $routes = new RouteCollection();
        $routes->add('reset', new Route('/reset-password/reset/{token}'));

        // The context of a request that arrived with a forged Host header.
        $this->router = new Router(new ClosureLoader(), static fn (): RouteCollection => $routes, [], new RequestContext(host: 'evil.example'));

        $this->requestStack = new RequestStack();
        $this->requestStack->push(Request::create('http://evil.example/reset-password'));
    }

    #[Override]
    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
    }

    public function testTheBaseUrlReplacesTheRequestHost(): void
    {
        $url = $this->context('https://app.example.com')->build($this->resetUrl(...));

        self::assertSame('https://app.example.com/reset-password/reset/abc', $url);
    }

    public function testTheRouterGoesBackToTheRequestHostAfterwards(): void
    {
        $this->context('https://app.example.com')->build($this->resetUrl(...));

        self::assertSame('http://evil.example/reset-password/reset/abc', $this->resetUrl());
    }

    public function testABaseUrlWithAPathKeepsIt(): void
    {
        $url = $this->context('https://example.com/app')->build($this->resetUrl(...));

        self::assertSame('https://example.com/app/reset-password/reset/abc', $url);
    }

    public function testWithNothingRestrictingTheHostNoLinkIsBuilt(): void
    {
        $this->expectException(UntrustedLinkHostException::class);

        $this->context(null)->build($this->resetUrl(...));
    }

    /**
     * Symfony has already turned away any host that does not match, which also keeps per-tenant
     * custom domains on their own domain.
     */
    public function testTrustedHostsAreEnoughToUseTheRequestHost(): void
    {
        Request::setTrustedHosts(['^evil\.example$']);

        self::assertSame('http://evil.example/reset-password/reset/abc', $this->context(null)->build($this->resetUrl(...)));
    }

    public function testDebugModeUsesTheRequestHostAsIs(): void
    {
        self::assertSame('http://evil.example/reset-password/reset/abc', $this->context(null, debug: true)->build($this->resetUrl(...)));
    }

    /**
     * A console command or a worker has no Host header to forge; the router uses
     * `framework.router.default_uri`.
     */
    public function testOutsideARequestTheRouterContextIsUsed(): void
    {
        $this->requestStack->pop();

        self::assertSame('http://evil.example/reset-password/reset/abc', $this->context(null)->build($this->resetUrl(...)));
    }

    private function resetUrl(): string
    {
        return $this->router->generate('reset', [
            'token' => 'abc',
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function context(?string $baseUrl, bool $debug = false): EmailLinkContext
    {
        return new EmailLinkContext($this->router, $this->requestStack, $baseUrl, $debug);
    }
}
