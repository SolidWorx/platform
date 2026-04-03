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

namespace SolidWorx\Platform\SaasBundle\Trial;

use Override;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Entity\Trial;
use SolidWorx\Platform\SaasBundle\Repository\TrialRepository;

final readonly class TrialManager implements TrialManagerInterface
{
    public function __construct(
        private TrialRepository $trialRepository,
    ) {
    }

    #[Override]
    public function userHasTrial(TrialUserInterface $user): bool
    {
        return $this->trialRepository->userHasTrial($user);
    }

    #[Override]
    public function createTrial(TrialUserInterface $user, Subscription $subscription): Trial
    {
        return $this->trialRepository->createTrial($user, $subscription);
    }
}
