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

namespace SolidWorx\Platform\SaasBundle\Entity;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SolidWorx\Platform\Component\Uid\EmptyUlid;
use SolidWorx\Platform\SaasBundle\Enum\SubscriptionInterval;
use SolidWorx\Platform\SaasBundle\Enum\SubscriptionStatus;
use SolidWorx\Platform\SaasBundle\Repository\SubscriptionRepository;
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: Subscription::TABLE_NAME)]
#[ORM\Index(columns: ['status'])]
class Subscription
{
    public const string TABLE_NAME = 'saas_subscription';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    private Ulid $id;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, precision: 3)]
    private DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, precision: 3)]
    private DateTimeImmutable $endDate;

    #[ORM\ManyToOne(targetEntity: SubscribableInterface::class, inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(name: 'subscriber_id', referencedColumnName: 'id', nullable: false)]
    private SubscribableInterface $subscriber;

    #[ORM\ManyToOne(targetEntity: Plan::class, inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(name: 'plan_id', referencedColumnName: 'id', nullable: false)]
    private Plan $plan;

    #[ORM\Column(type: Types::STRING, length: 45, enumType: SubscriptionStatus::class)]
    private SubscriptionStatus $status = SubscriptionStatus::PENDING;

    #[ORM\Column(type: Types::STRING, length: 45, enumType: SubscriptionInterval::class)]
    private SubscriptionInterval $interval = SubscriptionInterval::MONTHLY;

    /**
     * @var Collection<int, SubscriptionLog>
     */
    #[ORM\OneToMany(targetEntity: SubscriptionLog::class, mappedBy: 'subscription')]
    #[ORM\OrderBy([
        'createdAt' => 'DESC',
    ])]
    private Collection $logs;

    /*
     * @TODO: The following fields can be added to extend the Subscription model:
     *
     * - `trial_start_date`: DateTimeInterface
     * - `trial_end_date`: DateTimeInterface
     * - `cancellation_date`: DateTimeInterface
     * - `cancellation_reason`: string
     * - `discount_percent`: string (Used when adding a discount to the subscription, will always deduct x amount from the plan price)
     * - `special_price`: int (Used when adding a special price to the subscription, overrides the default price on the plan)
     */

    public function __construct()
    {
        $this->id = EmptyUlid::create();
        $this->logs = new ArrayCollection();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getStartDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(DateTimeInterface $startDate): static
    {
        $this->startDate = DateTimeImmutable::createFromInterface($startDate);

        return $this;
    }

    public function getEndDate(): DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(DateTimeInterface $endDate): static
    {
        $this->endDate = DateTimeImmutable::createFromInterface($endDate);

        return $this;
    }

    public function getPlan(): Plan
    {
        return $this->plan;
    }

    public function setPlan(Plan $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    public function getSubscriber(): SubscribableInterface
    {
        return $this->subscriber;
    }

    public function setSubscriber(SubscribableInterface $subscriber): static
    {
        $this->subscriber = $subscriber;

        return $this;
    }

    public function getStatus(): SubscriptionStatus
    {
        return $this->status;
    }

    public function setStatus(SubscriptionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getInterval(): SubscriptionInterval
    {
        return $this->interval;
    }

    public function setInterval(SubscriptionInterval $interval): static
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * @return Collection<int, SubscriptionLog>
     */
    public function getLogs(): Collection
    {
        return $this->logs;
    }

    public function addLog(SubscriptionLog $log): static
    {
        if (! $this->logs->contains($log)) {
            $this->logs->add($log);
            $log->setSubscription($this);
        }

        return $this;
    }
}
