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

namespace SolidWorx\Platform\PlatformBundle\Controller\Tenant;

use InvalidArgumentException;
use SolidWorx\Platform\PlatformBundle\Attributes\WithoutTenant;
use SolidWorx\Platform\PlatformBundle\Controller\BaseController;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Repository\TenantRepository;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use SolidWorx\Platform\PlatformBundle\Response\RedirectResponse;
use SolidWorx\Platform\PlatformBundle\Security\Voter\TenantVoter;
use SolidWorx\Platform\PlatformBundle\Tenant\Scope\TenantScopeGuardListener;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantLock;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantSessionStorage;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;
use function is_string;

/**
 * Lets an authenticated user pick the tenant they want to work in; the choice is stored in the
 * session and picked up by the {@see \SolidWorx\Platform\PlatformBundle\Tenant\Resolver\SessionTenantResolver}.
 *
 * Exempt from the scope guard, since this is where the guard sends people. Forbidden outright while
 * the tenant is locked to the request — on a custom domain there is nothing to choose between.
 */
#[AsTaggedItem('controller.service_arguments')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[WithoutTenant]
final class SelectTenant extends BaseController
{
    private const string CSRF_TOKEN_ID = 'tenant_select';

    public function __construct(
        private readonly UserTenantRepository $userTenantRepository,
        private readonly TenantRepository $tenantRepository,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly TenantSessionStorage $sessionStorage,
        private readonly TenantRedirector $redirector,
        private readonly TenantLock $tenantLock,
        #[Autowire(param: 'solidworx_platform_ui.template.tenant_select')]
        private readonly string $template,
    ) {
    }

    #[Route(path: '/tenant/select', name: TenantScopeGuardListener::SELECT_ROUTE, methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();

        if (! $user instanceof UserInterface) {
            throw $this->createAccessDeniedException();
        }

        if ($this->tenantLock->isLocked()) {
            throw $this->createAccessDeniedException('The workspace is fixed by the domain and cannot be changed.');
        }

        if ($request->isMethod(Request::METHOD_POST)) {
            return $this->select($request);
        }

        return $this->render($this->template, [
            'tenants' => $this->userTenantRepository->findTenantsForUser($user),
        ]);
    }

    private function select(Request $request): RedirectResponse
    {
        try {
            $token = $request->request->get('_token');

            if (! is_string($token) || ! $this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $submitted = $request->request->get('tenant');

            if (! is_string($submitted)) {
                throw $this->createNotFoundException();
            }

            try {
                $tenantId = Ulid::fromString($submitted);
            } catch (InvalidArgumentException) {
                throw $this->createNotFoundException();
            }

            $tenant = $this->tenantRepository->find($tenantId);

            if ($tenant === null) {
                throw $this->createNotFoundException();
            }

            $this->denyAccessUnlessGranted(TenantVoter::TENANT_ACCESS, $tenant);

            $this->sessionStorage->setTenantId($tenant->getId());

            return $this->redirector->redirectAfterSelection();
        } finally {
            $this->csrfTokenManager->removeToken(self::CSRF_TOKEN_ID);
        }
    }
}
