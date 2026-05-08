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
use SolidWorx\Platform\PlatformBundle\Feature\SubscribableInterface;
use SolidWorx\Platform\SaasBundle\Enum\SubscriptionStatus;
use SolidWorx\Platform\SaasBundle\Repository\SubscriptionRepository;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\NilUlid;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: Subscription::TABLE_NAME)]
#[ORM\Index(fields: ['status'])]
#[ORM\Index(fields: ['subscriptionId'])]
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

    #[ORM\ManyToOne(targetEntity: SubscribableInterface::class)]
    #[ORM\JoinColumn(name: 'subscriber_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private SubscribableInterface $subscriber;

    #[ORM\ManyToOne(targetEntity: Plan::class, inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(name: 'plan_id', referencedColumnName: 'id', nullable: false)]
    private Plan $plan;

    #[ORM\Column(type: Types::STRING, length: 45, enumType: SubscriptionStatus::class)]
    private SubscriptionStatus $status = SubscriptionStatus::PENDING;

    /**
     * Unique subscription id. Can either be set manually or set from an external system
     */
    #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: true)]
    private ?string $subscriptionId = null;

    /**
     * Plan the subscription should switch to at the end of the current paid
     * period. Set when a downgrade is scheduled; cleared once the switch
     * has been applied or the user resumes the current plan.
     */
    #[ORM\ManyToOne(targetEntity: Plan::class)]
    #[ORM\JoinColumn(name: 'pending_plan_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Plan $pendingPlan = null;

    /**
     * Date at which the pending plan switch should take effect. Mirrors the
     * payment provider's reported period end.
     */
    #[ORM\Column(name: 'pending_plan_change_at', type: Types::DATETIMETZ_IMMUTABLE, precision: 3, nullable: true)]
    private ?DateTimeImmutable $pendingPlanChangeAt = null;

    /**
     * @var Collection<int, SubscriptionLog>
     */
    #[ORM\OneToMany(targetEntity: SubscriptionLog::class, mappedBy: 'subscription', fetch: 'EXTRA_LAZY')]
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
        $this->id = new NilUlid();
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

    public function getSubscriptionId(): ?string
    {
        return $this->subscriptionId;
    }

    public function setSubscriptionId(?string $subscriptionId): static
    {
        $this->subscriptionId = $subscriptionId;

        return $this;
    }

    /**
     * Whether this subscription is tracked by an external payment provider.
     * Free-plan subscriptions are activated in-app and never carry an
     * external id; paid subscriptions get one once the provider issues it.
     */
    public function isExternallyBilled(): bool
    {
        return $this->subscriptionId !== null && $this->subscriptionId !== '';
    }

    public function getPendingPlan(): ?Plan
    {
        return $this->pendingPlan;
    }

    public function setPendingPlan(?Plan $pendingPlan): static
    {
        $this->pendingPlan = $pendingPlan;

        return $this;
    }

    public function getPendingPlanChangeAt(): ?DateTimeImmutable
    {
        return $this->pendingPlanChangeAt;
    }

    public function setPendingPlanChangeAt(?DateTimeImmutable $pendingPlanChangeAt): static
    {
        $this->pendingPlanChangeAt = $pendingPlanChangeAt;

        return $this;
    }

    public function hasPendingPlanChange(): bool
    {
        return $this->pendingPlan instanceof Plan;
    }
}
