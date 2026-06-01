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

namespace SolidWorx\Platform\PlatformBundle\Tenant;

use Symfony\Component\Uid\Ulid;
use SolidWorx\Platform\PlatformBundle\Exception\TenantAccessDeniedException;
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use SolidWorx\Platform\PlatformBundle\Tenant\Event\TenantSwitchedEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use function sprintf;

/**
 * Verifies that the authenticated user is a member of the tenant being entered, vetoing the switch
 * otherwise.
 *
 * This is the single, uniform membership check for every resolver. It runs at a high priority so it
 * fires before the filter synchronizer and before the context commits the change — a denied switch
 * therefore never applies the tenant nor enables the Doctrine filter.
 *
 * The check is skipped when there is no authenticated platform user (anonymous request on a custom
 * domain, console command, message worker), where the domain or system is the trust anchor.
 */
#[AsEventListener(event: TenantSwitchedEvent::class, priority: 256)]
final readonly class TenantAccessValidationListener
{
    public function __construct(
        private Security $security,
        private UserTenantRepository $userTenantRepository,
        #[Autowire(param: 'solidworx_platform.multi_tenancy.validate_user_access')]
        private bool $validateUserAccess,
    ) {
    }

    public function __invoke(TenantSwitchedEvent $event): void
    {
        if (! $this->validateUserAccess) {
            return;
        }

        $tenantId = $event->getTenantId();

        if (!$tenantId instanceof Ulid) {
            return;
        }

        $user = $this->security->getUser();

        if (! $user instanceof User) {
            return;
        }

        $userId = $user->getId();

        if (!$userId instanceof Ulid) {
            return;
        }

        if (! $this->userTenantRepository->hasAccess($userId, $tenantId)) {
            throw new TenantAccessDeniedException(sprintf(
                'The current user is not a member of tenant "%s".',
                $tenantId->toRfc4122(),
            ));
        }
    }
}
