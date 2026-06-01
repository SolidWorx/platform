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
use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @extends EntityRepository<TenantInterface>
 */
class TenantRepository extends EntityRepository
{
    /**
     * @param class-string<TenantInterface> $className
     */
    public function __construct(
        ManagerRegistry $registry,
        #[Autowire(param: 'solidworx_platform.multi_tenancy.models.tenant')]
        string $className = Tenant::class,
    ) {
        parent::__construct($registry, $className);
    }

    public function findOneByDomain(string $domain): ?TenantInterface
    {
        return $this->findOneBy([
            'domain' => $domain,
        ]);
    }
}
