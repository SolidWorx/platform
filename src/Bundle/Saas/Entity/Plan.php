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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use SolidWorx\Platform\SaasBundle\Repository\PlanRepository;
use Stringable;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\NilUlid;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: PlanRepository::class)]
#[ORM\Table(name: Plan::TABLE_NAME)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(fields: ['planId'])]
#[ORM\Index(fields: ['planId'])]
class Plan implements Stringable
{
    final public const string TABLE_NAME = 'saas_plan';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    private Ulid $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    /**
     * Unique value representing the plan id. This can be a unique value set by the user,
     * or an external value (E.G variantId from LemonSqueezy)
     */
    #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: false)]
    private string $planId;

    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(type: Types::INTEGER)]
    private int $price;

    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'plan', orphanRemoval: true)]
    private Collection $subscriptions;

    public function __construct()
    {
        $this->id = new NilUlid();
        $this->subscriptions = new ArrayCollection();
    }

    #[Override]
    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
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

    public function getPlanId(): string
    {
        return $this->planId;
    }

    public function setPlanId(string $planId): static
    {
        $this->planId = $planId;

        return $this;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }
}
