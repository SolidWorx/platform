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

namespace SolidWorx\Platform\PlatformBundle\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\NilUlid;
use Symfony\Component\Uid\Ulid;

/**
 * Membership record linking a user to a tenant they may operate within.
 */
#[ORM\Entity(repositoryClass: UserTenantRepository::class)]
#[ORM\Table(name: UserTenant::TABLE_NAME)]
#[ORM\UniqueConstraint(name: 'uniq_user_tenant', fields: ['userId', 'tenant'])]
class UserTenant
{
    final public const string TABLE_NAME = 'platform_user_tenant';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    private Ulid $id;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(#[ORM\Column(name: 'user_id', type: UlidType::NAME)]
    private Ulid $userId, #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant)
    {
        $this->id = new NilUlid();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getUserId(): Ulid
    {
        return $this->userId;
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
