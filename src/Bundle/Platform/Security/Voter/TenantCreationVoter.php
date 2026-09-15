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

namespace SolidWorx\Platform\PlatformBundle\Security\Voter;

use Override;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantLock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Decides whether a user may create a workspace — their first, or another one.
 *
 * Everything that offers creation asks for `TENANT_CREATE`: the switcher and the selection page to
 * decide whether to show the link, the onboarding controller through `#[IsGranted]`, and
 * {@see \SolidWorx\Platform\PlatformBundle\Tenant\Scope\TenantScopeResolver} to decide where to send
 * a user with no workspace. A refusal therefore cannot be routed around by going straight to the URL.
 *
 * This voter refuses for the two reasons the platform itself knows about: onboarding turned off in
 * the configuration, and a request pinned to a tenant by its domain, where a new workspace could not
 * be entered afterwards. Application limits — a plan cap, invite-only, a paid upgrade — belong in
 * your own voter for the same attribute, which the platform makes decisive by deciding
 * `TENANT_CREATE` unanimously.
 *
 * @extends Voter<string, mixed>
 */
final class TenantCreationVoter extends Voter
{
    public const string TENANT_CREATE = 'TENANT_CREATE';

    public function __construct(
        private readonly TenantLock $tenantLock,
        #[Autowire(param: 'solidworx_platform.multi_tenancy.onboarding.enabled')]
        private readonly bool $onboardingEnabled,
    ) {
    }

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::TENANT_CREATE;
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (! $token->getUser() instanceof UserInterface) {
            $vote?->addReason('Only a signed-in user can create a workspace.');

            return false;
        }

        if (! $this->onboardingEnabled) {
            $vote?->addReason('Creating workspaces is disabled.');

            return false;
        }

        if ($this->tenantLock->isLocked()) {
            $vote?->addReason('The workspace is fixed by the domain and cannot be changed.');

            return false;
        }

        return true;
    }
}
