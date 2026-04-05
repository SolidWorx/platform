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

use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Entity\Trial;
use SolidWorx\Platform\SaasBundle\Trial\TrialUserInterface;

interface TrialRepositoryInterface
{
    public function userHasTrial(TrialUserInterface $user): bool;

    /**
     * Persists a new Trial entity without flushing.
     * The caller is responsible for flushing the EntityManager.
     */
    public function createTrial(TrialUserInterface $user, Subscription $subscription): Trial;
}
