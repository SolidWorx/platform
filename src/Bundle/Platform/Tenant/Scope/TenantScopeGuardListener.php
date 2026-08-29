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

namespace SolidWorx\Platform\PlatformBundle\Tenant\Scope;

use SolidWorx\Platform\PlatformBundle\Attributes\WithoutTenant;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantSessionStorage;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Twig\Environment;
use function in_array;

/**
 * Ensures an authenticated user always has a tenant in scope, sending them to selection or
 * onboarding when they do not.
 *
 * Listens on `kernel.controller` rather than `kernel.request` on purpose: by then the controller is
 * resolved, so {@see ControllerEvent::getAttributes()} exposes the {@see WithoutTenant} opt-out
 * without a route allowlist to maintain. The tenant itself has already been established by
 * {@see \SolidWorx\Platform\PlatformBundle\Tenant\TenantRequestListener} on `kernel.request`.
 *
 * Anonymous requests are ignored outright, which covers the login page, the two-factor challenge,
 * password reset and registration without naming any of them.
 */
#[AsEventListener(event: KernelEvents::CONTROLLER)]
final readonly class TenantScopeGuardListener
{
    public const string SELECT_ROUTE = 'solidworx_platform_tenant_select';

    public const string ONBOARDING_ROUTE = 'solidworx_platform_tenant_onboarding';

    public function __construct(
        private Security $security,
        private TokenStorageInterface $tokenStorage,
        private TenantScopeResolver $scopeResolver,
        private TenantSessionStorage $sessionStorage,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
        #[Autowire(param: 'solidworx_platform_ui.template.tenant_no_access')]
        private string $noAccessTemplate,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $user = $this->authenticatedUser();

        if (! $user instanceof UserInterface) {
            return;
        }

        if ($event->getAttributes(WithoutTenant::class) !== []) {
            return;
        }

        $outcome = $this->scopeResolver->resolve($user);

        if ($outcome->allowsRequest()) {
            return;
        }

        $response = $this->respondTo($outcome, $event->getRequest());

        $event->setController(static fn (): Response => $response);
    }

    /**
     * The user, but only once they are *fully* authenticated.
     *
     * A half-authenticated two-factor token must not be pushed towards tenant selection: the user
     * has not finished proving who they are, so which tenants they belong to is not yet a
     * meaningful question.
     */
    private function authenticatedUser(): ?UserInterface
    {
        if (! $this->tokenStorage->getToken() instanceof TokenInterface) {
            return null;
        }

        if (! $this->security->isGranted('IS_AUTHENTICATED_FULLY')) {
            return null;
        }

        $user = $this->security->getUser();

        return $user instanceof UserInterface ? $user : null;
    }

    private function respondTo(TenantScopeOutcome $outcome, Request $request): Response
    {
        if ($outcome === TenantScopeOutcome::NoAccess) {
            return $this->deny($request, 'You do not have access to any workspace.');
        }

        $route = $outcome === TenantScopeOutcome::NeedsOnboarding
            ? self::ONBOARDING_ROUTE
            : self::SELECT_ROUTE;

        // A redirect to an HTML page is useless to a fetch() caller, so anything that is not a
        // browser navigation gets a machine-readable refusal instead.
        if (! $this->expectsHtml($request)) {
            return $this->deny($request, 'No workspace is selected.');
        }

        $this->rememberTarget($request);

        return new RedirectResponse($this->urlGenerator->generate($route));
    }

    private function deny(Request $request, string $message): Response
    {
        if ($this->expectsHtml($request)) {
            return new Response(
                $this->twig->render($this->noAccessTemplate, [
                    'message' => $message,
                ]),
                Response::HTTP_FORBIDDEN,
            );
        }

        return new JsonResponse([
            'error' => 'tenant_scope_required',
            'message' => $message,
        ], Response::HTTP_FORBIDDEN);
    }

    /**
     * Records where the user was headed, so selection and onboarding can return them to it.
     *
     * Only plain GET navigations are worth remembering: replaying a POST after a detour through
     * another page would silently re-submit it.
     */
    private function rememberTarget(Request $request): void
    {
        if (! $request->isMethod(Request::METHOD_GET)) {
            return;
        }

        $target = $request->getPathInfo();
        $query = $request->getQueryString();

        $this->sessionStorage->setTargetPath($query === null ? $target : $target . '?' . $query);
    }

    private function expectsHtml(Request $request): bool
    {
        if ($request->isXmlHttpRequest()) {
            return false;
        }

        if ($request->getRequestFormat(null) === 'json') {
            return false;
        }

        return in_array('text/html', $request->getAcceptableContentTypes(), true)
            || $request->getAcceptableContentTypes() === []
            || in_array('*/*', $request->getAcceptableContentTypes(), true);
    }
}
