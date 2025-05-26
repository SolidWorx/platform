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

namespace SolidWorx\Platform\SaasBundle\Doctrine\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use SolidWorx\Platform\SaasBundle\Entity\Plan;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Plan::class)]
final readonly class PlanIdSubscriber
{
    public function __construct(
        private SluggerInterface $slugger,
    ) {
    }

    public function prePersist(Plan $plan): void
    {
        if ($plan->getPlanId() === '') {
            $plan->setPlanId(strtolower($this->slugger->slug($plan->getName())));
        }
    }
}
