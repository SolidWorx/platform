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

use SolidWorx\Platform\PlatformBundle\Exception\TenantAccessDeniedException;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantChoice;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantManager;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantSessionStorage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use function count;

/**
 * Decides how a request without a tenant in scope should be brought into one.
 *
 * Deliberately free of HTTP: it answers *what* should happen, and
 * {@see TenantScopeGuardListener} decides how to say it. That split keeps the interesting logic
 * testable without a kernel.
 *
 * Auto-selection runs on every tenant-less request rather than only at login. It is self-healing —
 * a session whose tenant was dropped, a user narrowed down to a single tenant mid-session, and a
 * functional test booting straight into a controller all converge on the same correct state.
 */
final readonly class TenantScopeResolver
{
    public function __construct(
        private TenantContext $tenantContext,
        private TenantManager $tenantManager,
        private TenantSessionStorage $sessionStorage,
        private UserTenantRepository $userTenantRepository,
        #[Autowire(param: 'solidworx_platform.multi_tenancy.onboarding.enabled')]
        private bool $onboardingEnabled,
    ) {
    }

    public function resolve(UserInterface $user): TenantScopeOutcome
    {
        if ($this->tenantContext->hasTenant()) {
            return TenantScopeOutcome::AlreadyScoped;
        }

        $tenants = $this->userTenantRepository->findTenantsForUser($user);

        return match (count($tenants)) {
            0 => $this->onboardingEnabled ? TenantScopeOutcome::NeedsOnboarding : TenantScopeOutcome::NoAccess,
            1 => $this->autoSelect($tenants[0]),
            default => TenantScopeOutcome::NeedsSelection,
        };
    }

    /**
     * Enters the user's only tenant and remembers it for subsequent requests.
     *
     * Goes through {@see TenantManager} rather than the context directly, so the switch is still
     * validated: auto-selection is a convenience, never a way around the membership check.
     */
    private function autoSelect(TenantChoice $choice): TenantScopeOutcome
    {
        try {
            $this->tenantManager->switchTo($choice->id);
        } catch (TenantAccessDeniedException) {
            // The membership row that produced this choice was revoked between the query and the
            // switch. Treat it as having no tenants at all rather than surfacing a 403 for a
            // tenant the user never chose.
            $this->sessionStorage->clearTenantId();

            return $this->onboardingEnabled ? TenantScopeOutcome::NeedsOnboarding : TenantScopeOutcome::NoAccess;
        }

        $this->sessionStorage->setTenantId($choice->id);

        return TenantScopeOutcome::AutoSelected;
    }
}
