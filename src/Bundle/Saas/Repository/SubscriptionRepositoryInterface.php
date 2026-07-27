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

use DateTimeImmutable;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;

interface SubscriptionRepositoryInterface
{
    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string>|null $orderBy
     */
    public function findOneBy(array $criteria, array|null $orderBy = null): ?Subscription;

    public function save(object $entity, bool $flush = true): void;

    /**
     * Subscriptions still flagged TRIAL whose trial period has elapsed
     * (endDate at or before $now). A lapsed trial's status is never flipped,
     * so this date comparison is the source of truth.
     *
     * @return list<Subscription>
     */
    public function findExpiredTrials(DateTimeImmutable $now): array;
}
