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

use DateInterval;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SolidWorx\Platform\SaasBundle\Repository\PlanPriceRepository;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\NilUlid;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: PlanPriceRepository::class)]
#[ORM\Table(name: PlanPrice::TABLE_NAME)]
#[ORM\UniqueConstraint(fields: ['variantId'])]
#[ORM\Index(fields: ['variantId'])]
class PlanPrice
{
    final public const string TABLE_NAME = 'saas_plan_price';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Plan::class, inversedBy: 'prices')]
    #[ORM\JoinColumn(name: 'plan_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Plan $plan;

    /**
     * Unique value representing the price/variant id from the payment provider
     * (e.g. variant id from LemonSqueezy). The sentinel value '0' represents
     * the local-only free price.
     */
    #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: false)]
    private string $variantId;

    #[ORM\Column(type: Types::INTEGER)]
    private int $price = 0;

    /**
     * Billing renewal interval. Null for free prices that never renew.
     */
    #[ORM\Column(type: Types::DATEINTERVAL, nullable: true)]
    private ?DateInterval $interval = null;

    #[ORM\Column(type: Types::BOOLEAN, options: [
        'default' => true,
    ])]
    private bool $active = true;

    public function __construct()
    {
        $this->id = new NilUlid();
    }

    public function getId(): Ulid
    {
        return $this->id;
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

    public function getVariantId(): string
    {
        return $this->variantId;
    }

    public function setVariantId(string $variantId): static
    {
        $this->variantId = $variantId;

        return $this;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getInterval(): ?DateInterval
    {
        return $this->interval;
    }

    public function setInterval(?DateInterval $interval): static
    {
        $this->interval = $interval;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /**
     * A price is "free" when it has the local sentinel variant id and a zero
     * price — these subscriptions skip checkout and activate immediately.
     */
    public function isFree(): bool
    {
        return $this->price === 0 && $this->variantId === '0';
    }
}
