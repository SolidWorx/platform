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

namespace SolidWorx\Platform\SaasBundle\Repository;

use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use SolidWorx\Platform\PlatformBundle\Repository\EntityRepository;
use SolidWorx\Platform\SaasBundle\Entity\PlanPrice;
use Symfony\Component\Uid\Ulid;

/**
 * @template-extends EntityRepository<PlanPrice>
 */
class PlanPriceRepository extends EntityRepository implements PlanPriceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanPrice::class);
    }

    /**
     * @param string|PlanPrice|Ulid $id
     */
    #[Override]
    public function find(mixed $id, LockMode|int|null $lockMode = null, int|null $lockVersion = null): ?PlanPrice
    {
        if ($id instanceof PlanPrice) {
            return $id;
        }

        if (is_string($id)) {
            return $this->findOneBy([
                'variantId' => $id,
            ]);
        }

        return parent::find($id, $lockMode instanceof LockMode ? null : $lockMode, $lockVersion);
    }
}
