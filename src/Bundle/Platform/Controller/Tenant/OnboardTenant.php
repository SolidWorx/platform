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

use SolidWorx\Platform\PlatformBundle\Attributes\WithoutTenant;
use SolidWorx\Platform\PlatformBundle\Controller\BaseController;
use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use SolidWorx\Platform\PlatformBundle\Tenant\Onboarding\TenantOnboarder;
use SolidWorx\Platform\PlatformBundle\Tenant\Scope\TenantScopeGuardListener;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lets a user with no workspace create their first one.
 *
 * Exempt from the scope guard for the obvious reason — it is where the guard sends people. It is
 * also strictly a *first* workspace screen: a user who already has one is sent to the selection
 * page instead.
 */
#[AsTaggedItem('controller.service_arguments')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[WithoutTenant]
final class OnboardTenant extends BaseController
{
    /**
     * @param class-string<FormTypeInterface> $formType
     */
    public function __construct(
        private readonly TenantOnboarder $onboarder,
        private readonly UserTenantRepository $userTenantRepository,
        private readonly TenantRedirector $redirector,
        #[Autowire(param: 'solidworx_platform.multi_tenancy.onboarding.enabled')]
        private readonly bool $enabled,
        #[Autowire(param: 'solidworx_platform.multi_tenancy.onboarding.form_type')]
        private readonly string $formType,
        #[Autowire(param: 'solidworx_platform_ui.template.tenant_onboarding')]
        private readonly string $template,
    ) {
    }

    #[Route(path: '/tenant/onboarding', name: TenantScopeGuardListener::ONBOARDING_ROUTE, methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if (! $this->enabled) {
            throw $this->createNotFoundException('Tenant onboarding is disabled.');
        }

        $user = $this->getUser();

        if (! $user instanceof UserInterface) {
            throw $this->createAccessDeniedException();
        }

        if ($this->userTenantRepository->countTenantsForUser($user) > 0) {
            return $this->redirectToRoute(TenantScopeGuardListener::SELECT_ROUTE);
        }

        $form = $this->createForm($this->formType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tenant = $form->getData();

            if ($tenant instanceof TenantInterface) {
                $this->onboarder->onboard($tenant, $user);

                return $this->redirector->redirectAfterSelection();
            }
        }

        return $this->render($this->template, [
            'form' => $form,
        ]);
    }
}
