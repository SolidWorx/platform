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

namespace SolidWorx\Platform\PlatformBundle\Tenant\Onboarding;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Model\UserTenantInterface;
use SolidWorx\Platform\PlatformBundle\Tenant\Event\TenantCreatedEvent;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantManager;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantSessionStorage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The stock onboarding behaviour: create the workspace, make its creator a member, and enter it.
 *
 * Neither the tenant nor the membership is itself tenant-aware, so both can be written while no
 * tenant is in scope without tripping the write guard.
 */
final readonly class DefaultTenantOnboarder implements TenantOnboarder
{
    /**
     * @param class-string<UserTenantInterface> $userTenantClass
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TenantManager $tenantManager,
        private TenantSessionStorage $sessionStorage,
        private EventDispatcherInterface $eventDispatcher,
        #[Autowire(param: 'solidworx_platform.multi_tenancy.models.user_tenant')]
        private string $userTenantClass,
    ) {
    }

    #[Override]
    public function onboard(TenantInterface $tenant, UserInterface $user): void
    {
        $tenant->setCreatedById($user->getId());

        $this->entityManager->persist($tenant);
        $this->entityManager->persist(new $this->userTenantClass($user, $tenant));

        // Flushed before entering the tenant: the membership row is what the access-validation
        // listener checks, so it has to be readable by the time the switch happens.
        $this->entityManager->flush();

        $this->tenantManager->switchTo($tenant);
        $this->sessionStorage->setTenantId($tenant->getId());

        $this->eventDispatcher->dispatch(new TenantCreatedEvent($tenant, $user));
    }
}
