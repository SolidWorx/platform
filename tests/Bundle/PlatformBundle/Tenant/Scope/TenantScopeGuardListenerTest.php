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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Tenant\Scope;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Attributes\WithoutTenant;
use SolidWorx\Platform\PlatformBundle\Entity\User;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use SolidWorx\Platform\PlatformBundle\Tenant\Scope\TenantScopeGuardListener;
use SolidWorx\Platform\PlatformBundle\Tenant\Scope\TenantScopeOutcome;
use SolidWorx\Platform\PlatformBundle\Tenant\Scope\TenantScopeResolver;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantChoice;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantLock;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantManager;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantSessionStorage;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Controller\ExemptController;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Controller\GuardedController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Ulid;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(TenantScopeGuardListener::class)]
#[CoversClass(WithoutTenant::class)]
#[UsesClass(TenantScopeResolver::class)]
#[UsesClass(TenantScopeOutcome::class)]
#[UsesClass(TenantContext::class)]
#[UsesClass(TenantManager::class)]
#[UsesClass(TenantLock::class)]
#[UsesClass(TenantChoice::class)]
#[UsesClass(TenantSessionStorage::class)]
final class TenantScopeGuardListenerTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
    }

    public function testIgnoresSubRequests(): void
    {
        $event = $this->createEvent(requestType: HttpKernelInterface::SUB_REQUEST);

        $this->createListener([])($event);

        $this->assertNull($this->responseOf($event));
    }

    public function testIgnoresAnonymousRequests(): void
    {
        $event = $this->createEvent();

        $this->createListener([], authenticated: false)($event);

        $this->assertNull($this->responseOf($event));
    }

    /**
     * A half-authenticated two-factor token must not be pushed towards tenant selection — the user
     * has not finished proving who they are.
     */
    public function testIgnoresPartiallyAuthenticatedRequests(): void
    {
        $event = $this->createEvent();

        $this->createListener([], fullyAuthenticated: false)($event);

        $this->assertNull($this->responseOf($event));
    }

    public function testIgnoresAControllerExemptedByItsClass(): void
    {
        $event = $this->createEvent(controller: new ExemptController());

        $this->createListener([])($event);

        $this->assertNull($this->responseOf($event));
    }

    public function testIgnoresAnActionExemptedByItsMethod(): void
    {
        $event = $this->createEvent(controller: [new GuardedController(), 'exemptAction']);

        $this->createListener([])($event);

        $this->assertNull($this->responseOf($event));
    }

    public function testLetsAnAutoSelectedRequestThrough(): void
    {
        $event = $this->createEvent();

        $this->createListener([new TenantChoice(new Ulid(), 'Acme')])($event);

        $this->assertNull($this->responseOf($event));
    }

    public function testRedirectsToSelectionWithSeveralTenants(): void
    {
        $event = $this->createEvent();

        $this->createListener([
            new TenantChoice(new Ulid(), 'Acme'),
            new TenantChoice(new Ulid(), 'Globex'),
        ])($event);

        $response = $this->responseOf($event);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/generated/' . TenantScopeGuardListener::SELECT_ROUTE, $response->getTargetUrl());
    }

    public function testRedirectsToOnboardingWithNoTenants(): void
    {
        $event = $this->createEvent();

        $this->createListener([])($event);

        $response = $this->responseOf($event);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/generated/' . TenantScopeGuardListener::ONBOARDING_ROUTE, $response->getTargetUrl());
    }

    public function testRendersTheNoAccessPageWhenOnboardingIsDisabled(): void
    {
        $event = $this->createEvent();

        $this->createListener([], onboardingEnabled: false)($event);

        $response = $this->responseOf($event);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertStringContainsString('You do not have access to any workspace.', (string) $response->getContent());
    }

    /**
     * A redirect to an HTML page tells a fetch() caller nothing useful, so an XHR gets a
     * machine-readable refusal instead.
     */
    public function testRefusesAnXhrRequestInsteadOfRedirecting(): void
    {
        $request = Request::create('/invoices');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $event = $this->createEvent(request: $request);

        $this->createListener([])($event);

        $response = $this->responseOf($event);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertSame(
            [
                'error' => 'tenant_scope_required',
                'message' => 'No workspace is selected.',
            ],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testRefusesAJsonRequestInsteadOfRedirecting(): void
    {
        $request = Request::create('/invoices');
        $request->headers->set('Accept', 'application/json');

        $event = $this->createEvent(request: $request);

        $this->createListener([])($event);

        $this->assertInstanceOf(JsonResponse::class, $this->responseOf($event));
    }

    public function testRemembersWhereTheUserWasHeaded(): void
    {
        $event = $this->createEvent(request: Request::create('/invoices/123?page=2'));

        $this->createListener([])($event);

        $this->assertSame('/invoices/123?page=2', $this->session->get('_tenant_scope_target'));
    }

    /**
     * Replaying a POST after a detour through onboarding would silently re-submit it.
     */
    public function testDoesNotRememberANonGetRequest(): void
    {
        $event = $this->createEvent(request: Request::create('/invoices', 'POST'));

        $this->createListener([])($event);

        $this->assertFalse($this->session->has('_tenant_scope_target'));
    }

    /**
     * @param list<TenantChoice> $tenants
     */
    private function createListener(
        array $tenants,
        bool $authenticated = true,
        bool $fullyAuthenticated = true,
        bool $onboardingEnabled = true,
    ): TenantScopeGuardListener {
        $user = $authenticated ? $this->user() : null;

        $security = self::createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturn($fullyAuthenticated);

        $repository = self::createStub(UserTenantRepository::class);
        $repository->method('findTenantsForUser')->willReturn($tenants);

        $request = new Request();
        $request->setSession($this->session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $context = new TenantContext(new EventDispatcher());
        $sessionStorage = new TenantSessionStorage($requestStack, '_tenant_id');

        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route): string => '/generated/' . $route,
        );

        return new TenantScopeGuardListener(
            $security,
            $this->tokenStorage($user),
            new TenantScopeResolver(
                $context,
                new TenantManager($context, self::createStub(EntityManagerInterface::class), new TenantLock()),
                $sessionStorage,
                $repository,
                $onboardingEnabled,
            ),
            $sessionStorage,
            $urlGenerator,
            new Environment(new ArrayLoader([
                'no_access.html.twig' => '<p>{{ message }}</p>',
            ])),
            'no_access.html.twig',
        );
    }

    private function user(): UserInterface
    {
        $user = self::createStub(User::class);
        $user->method('getId')->willReturn(new Ulid());

        return $user;
    }

    private function tokenStorage(?UserInterface $user): TokenStorageInterface
    {
        $tokenStorage = new TokenStorage();

        if ($user instanceof UserInterface) {
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'main'));
        }

        return $tokenStorage;
    }

    /**
     * The controller is passed as a real invokable or `[$object, 'method']` pair rather than a
     * closure, because the guard reads {@see WithoutTenant} off it by reflection.
     */
    private function createEvent(
        ?Request $request = null,
        ?callable $controller = null,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): ControllerEvent {
        $request ??= Request::create('/invoices');

        if (! $request->hasSession()) {
            $request->setSession($this->session);
        }

        return new ControllerEvent(
            self::createStub(HttpKernelInterface::class),
            $controller ?? new GuardedController(),
            $request,
            $requestType,
        );
    }

    /**
     * The guard answers by swapping in a controller that returns the response, so the response is
     * whatever that controller produces.
     */
    private function responseOf(ControllerEvent $event): ?Response
    {
        $controller = $event->getController();

        if ($controller instanceof GuardedController || $controller instanceof ExemptController) {
            return null;
        }

        if (is_array($controller)) {
            return null;
        }

        $result = $controller();

        return $result instanceof Response ? $result : null;
    }
}
