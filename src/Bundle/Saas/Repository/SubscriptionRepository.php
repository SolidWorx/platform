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

use Doctrine\Persistence\ManagerRegistry;
use Override;
use SolidWorx\Platform\PlatformBundle\Repository\EntityRepository;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;

/**
 * @template-extends EntityRepository<Subscription>
 */
final class SubscriptionRepository extends EntityRepository implements SubscriptionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string>|null $orderBy
     */
    #[Override]
    public function findOneBy(array $criteria, array|null $orderBy = null): ?Subscription
    {
        $result = parent::findOneBy($criteria, $orderBy);
        assert($result === null || $result instanceof Subscription);

        return $result;
    }
}
