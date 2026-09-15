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
use SolidWorx\Platform\PlatformBundle\Security\Voter\TenantCreationVoter;
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
 * Creates a workspace: the first one for a user who has none, and every one after that.
 *
 * Exempt from the scope guard for the obvious reason — it is where the guard sends people — and it
 * is the same page in both cases, only reached differently: the guard redirects here, or the user
 * picks "create new workspace" in the switcher. A user who already has a workspace gets a cancel
 * link back to the application; one who does not has nowhere to cancel to.
 *
 * Whether creation is offered at all is {@see TenantCreationVoter}'s call, not this controller's:
 * the same `TENANT_CREATE` attribute guards this page, the switcher entry and the selection page, so
 * a refusal cannot be routed around by going straight to the URL. The 403 carries the voter's own
 * reason, which Symfony reads off the access decision.
 */
#[AsTaggedItem(index: 'controller.service_arguments')]
#[IsGranted(attribute: 'IS_AUTHENTICATED_FULLY')]
#[IsGranted(attribute: TenantCreationVoter::TENANT_CREATE)]
#[WithoutTenant]
final class OnboardTenant extends BaseController
{
    /**
     * @param class-string<FormTypeInterface<TenantInterface>> $formType A replacement form type must
     *                                                                   still produce a tenant
     */
    public function __construct(
        private readonly TenantOnboarder $onboarder,
        private readonly UserTenantRepository $userTenantRepository,
        private readonly TenantRedirector $redirector,
        #[Autowire(param: 'solidworx_platform.multi_tenancy.onboarding.form_type')]
        private readonly string $formType,
        #[Autowire(param: 'solidworx_platform_ui.template.tenant_onboarding')]
        private readonly string $template,
    ) {
    }

    #[Route(path: '/tenant/onboarding', name: TenantScopeGuardListener::ONBOARDING_ROUTE, methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();

        if (! $user instanceof UserInterface) {
            throw $this->createAccessDeniedException();
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
            // Cancelling is only meaningful with somewhere to go back to, which a user without a
            // workspace does not have — the guard would send them straight back here.
            'cancel_path' => $this->userTenantRepository->countTenantsForUser($user) > 0
                ? $this->redirector->defaultPath()
                : null,
        ]);
    }
}
