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
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use Symfony\Component\Uid\Ulid;

interface PlanRepositoryInterface
{
    /**
     * @param string|Plan|Ulid $id
     */
    public function find(mixed $id, LockMode|int|null $lockMode = null, int|null $lockVersion = null): ?Plan;
}
