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

namespace SolidWorx\Platform\PlatformBundle\Doctrine\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use SolidWorx\Platform\PlatformBundle\Exception\CrossTenantOperationException;
use SolidWorx\Platform\PlatformBundle\Exception\TenantAccessDeniedException;
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantAwareInterface;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Ulid;
use function array_merge;
use function sprintf;

/**
 * Rejects writes that would cross a tenant boundary.
 *
 * For every inserted or updated {@see TenantAwareInterface} entity, the entity's tenant must match
 * the tenant currently in scope. When no tenant is in scope (a deliberate cross-tenant batch
 * operation) the guard stands down. Optionally also verifies that the authenticated user is a
 * member of the tenant being written to.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final readonly class TenantWriteGuardListener
{
    public function __construct(
        private TenantContext $tenantContext,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private UserTenantRepository $userTenantRepository,
        #[Autowire(param: 'solidworx_platform.multi_tenancy.write_guard.check_user_access')]
        private bool $checkUserAccess,
    ) {
    }

    public function onFlush(): void
    {
        $tenantId = $this->tenantContext->getTenantId();
        if (!$tenantId instanceof Ulid) {
            return;
        }
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $entities = array_merge(
            $unitOfWork->getScheduledEntityInsertions(),
            $unitOfWork->getScheduledEntityUpdates(),
        );
        foreach ($entities as $entity) {
            if (! $entity instanceof TenantAwareInterface) {
                continue;
            }

            $entityTenantId = $entity->getTenantId();

            if (! $entityTenantId instanceof Ulid || ! $entityTenantId->equals($tenantId)) {
                throw CrossTenantOperationException::forEntity($entity::class);
            }
        }
        if ($entities !== []) {
            $this->assertUserAccess($tenantId);
        }
    }

    private function assertUserAccess(Ulid $tenantId): void
    {
        if (! $this->checkUserAccess) {
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
                'The current user is not a member of tenant "%s" and cannot write to it.',
                $tenantId->toRfc4122(),
            ));
        }
    }
}
