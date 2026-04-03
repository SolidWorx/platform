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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SolidWorx\Platform\SaasBundle\Repository\TrialRepository;
use SolidWorx\Platform\SaasBundle\Trial\TrialUserInterface;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\NilUlid;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: TrialRepository::class)]
#[ORM\Table(name: Trial::TABLE_NAME)]
class Trial
{
    final public const string TABLE_NAME = 'saas_trial';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    private Ulid $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\OneToOne(targetEntity: TrialUserInterface::class)]
    #[ORM\JoinColumn(nullable: false)]
    private TrialUserInterface $user;

    #[ORM\OneToOne(targetEntity: Subscription::class)]
    #[ORM\JoinColumn(onDelete: 'CASCADE', nullable: false)]
    private Subscription $subscription;

    public function __construct()
    {
        $this->id = new NilUlid();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUser(): TrialUserInterface
    {
        return $this->user;
    }

    public function setUser(TrialUserInterface $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(Subscription $subscription): static
    {
        $this->subscription = $subscription;

        return $this;
    }
}
