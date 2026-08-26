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

namespace SolidWorx\Platform\PlatformBundle\Model;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\NilUlid;
use Symfony\Component\Uid\Ulid;

/**
 * Mapped base for the membership entity linking a user to a tenant.
 *
 * Ships with a ready-to-use concrete entity ({@see \SolidWorx\Platform\PlatformBundle\Entity\UserTenant}).
 * To add custom fields, extend this class with your own `#[ORM\Entity]` and register it via
 * `platform.multi_tenancy.models.user_tenant`.
 */
#[ORM\MappedSuperclass]
abstract class UserTenant implements UserTenantInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    protected Ulid $id;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: UserInterface::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        protected UserInterface $user,
        #[ORM\ManyToOne(targetEntity: TenantInterface::class)]
        #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        protected TenantInterface $tenant
    ) {
        $this->id = new NilUlid();
        $this->createdAt = CarbonImmutable::now();
    }

    #[Override]
    public function getId(): Ulid
    {
        return $this->id;
    }

    #[Override]
    public function getUser(): UserInterface
    {
        return $this->user;
    }

    #[Override]
    public function getTenant(): TenantInterface
    {
        return $this->tenant;
    }

    #[Override]
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
