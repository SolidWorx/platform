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

namespace SolidWorx\Platform\PlatformBundle\Repository;

use Doctrine\Persistence\ManagerRegistry;
use SolidWorx\Platform\PlatformBundle\Entity\UserTenant;
use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use SolidWorx\Platform\PlatformBundle\Model\UserTenantInterface;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Ulid;

/**
 * @extends EntityRepository<UserTenantInterface>
 */
class UserTenantRepository extends EntityRepository
{
    /**
     * @param class-string<UserTenantInterface> $className
     */
    public function __construct(
        ManagerRegistry $registry,
        #[Autowire(param: 'solidworx_platform.multi_tenancy.models.user_tenant')]
        string $className = UserTenant::class,
    ) {
        parent::__construct($registry, $className);
    }

    public function hasAccess(Ulid $userId, TenantInterface|Ulid $tenant): bool
    {
        $tenantId = $tenant instanceof TenantInterface ? $tenant->getId() : $tenant;

        $count = $this->createQueryBuilder('ut')
            ->select('COUNT(ut.id)')
            ->where('ut.userId = :userId')
            ->andWhere('IDENTITY(ut.tenant) = :tenantId')
            ->setParameter('userId', $userId, UlidType::NAME)
            ->setParameter('tenantId', $tenantId, UlidType::NAME)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * @return list<TenantInterface>
     */
    public function findTenantsForUser(Ulid $userId): array
    {
        /** @var list<TenantInterface> */
        return $this->createQueryBuilder('ut')
            ->select('t')
            ->join('ut.tenant', 't')
            ->where('ut.userId = :userId')
            ->setParameter('userId', $userId, UlidType::NAME)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
