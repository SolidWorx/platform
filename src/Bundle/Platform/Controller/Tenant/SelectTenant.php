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
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\PlatformBundle\Repository\TenantRepository;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use SolidWorx\Platform\PlatformBundle\Security\Voter\TenantVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;
use function is_string;

/**
 * Lets an authenticated user pick the tenant they want to work in; the choice is stored in the
 * session and picked up by the {@see \SolidWorx\Platform\PlatformBundle\Tenant\Resolver\SessionTenantResolver}.
 */
#[AsTaggedItem('controller.service_arguments')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class SelectTenant extends AbstractController
{
    private const string CSRF_TOKEN_ID = 'tenant_select';

    public function __construct(
        private readonly UserTenantRepository $userTenantRepository,
        private readonly TenantRepository $tenantRepository,
        #[Autowire(param: 'solidworx_platform.multi_tenancy.session_key')]
        private readonly string $sessionKey,
    ) {
    }

    #[Route(path: '/tenant/select', name: 'solidworx_platform_tenant_select', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();
        $userId = $user instanceof User ? $user->getId() : null;

        if (! $userId instanceof Ulid) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            return $this->select($request);
        }

        return $this->render('@Ui/Tenant/select.html.twig', [
            'tenants' => $this->userTenantRepository->findTenantsForUser($userId),
        ]);
    }

    private function select(Request $request): RedirectResponse
    {
        $token = $request->request->get('_token');

        if (! is_string($token) || ! $this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token)) {
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

        $request->getSession()->set($this->sessionKey, $tenant->getId()->toRfc4122());

        return $this->redirectToRoute('solidworx_platform_tenant_select');
    }
}
