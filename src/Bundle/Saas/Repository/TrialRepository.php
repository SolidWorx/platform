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
use SolidWorx\Platform\SaasBundle\Entity\Trial;
use SolidWorx\Platform\SaasBundle\Trial\TrialUserInterface;
use Symfony\Bridge\Doctrine\Types\UlidType;

/**
 * @extends EntityRepository<Trial>
 */
final class TrialRepository extends EntityRepository implements TrialRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trial::class);
    }

    #[Override]
    public function userHasTrial(TrialUserInterface $user): bool
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.user = :user')
            ->setParameter('user', $user->getId(), UlidType::NAME);

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Persists a new Trial entity without flushing.
     * The caller is responsible for flushing the EntityManager.
     */
    #[Override]
    public function createTrial(TrialUserInterface $user, Subscription $subscription): Trial
    {
        $trial = Trial::create($user, $subscription);

        $this->getEntityManager()->persist($trial);

        return $trial;
    }
}
