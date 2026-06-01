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
use SolidWorx\Platform\PlatformBundle\Entity\Tenant;
use SolidWorx\Platform\PlatformBundle\Entity\UserTenant;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * @extends EntityRepository<UserTenant>
 */
class UserTenantRepository extends EntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserTenant::class);
    }

    public function hasAccess(Ulid $userId, Tenant|Ulid $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getId() : $tenant;

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
     * @return list<Tenant>
     */
    public function findTenantsForUser(Ulid $userId): array
    {
        /** @var list<Tenant> */
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
