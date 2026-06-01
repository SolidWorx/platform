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

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\NilUlid;
use Symfony\Component\Uid\Ulid;

/**
 * Mapped base for the tenant boundary entity.
 *
 * Ships with a ready-to-use concrete entity ({@see \SolidWorx\Platform\PlatformBundle\Entity\Tenant}).
 * To add custom fields, extend this class with your own `#[ORM\Entity]` and register it via
 * `platform.multi_tenancy.models.tenant`.
 */
#[ORM\MappedSuperclass]
abstract class Tenant implements TenantInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    protected Ulid $id;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    protected string $name;

    /**
     * The custom hostname mapped to this tenant, used for domain-based resolution.
     */
    #[ORM\Column(name: 'domain', type: Types::STRING, length: 255, unique: true, nullable: true)]
    protected ?string $domain = null;

    #[ORM\Column(name: 'created_by_id', type: UlidType::NAME, nullable: true)]
    protected ?Ulid $createdById = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $createdAt;

    public function __construct(string $name, ?Ulid $createdById = null)
    {
        $this->id = new NilUlid();
        $this->name = $name;
        $this->createdById = $createdById;
        $this->createdAt = new DateTimeImmutable();
    }

    #[Override]
    public function __toString(): string
    {
        return $this->name;
    }

    #[Override]
    public function getId(): Ulid
    {
        return $this->id;
    }

    #[Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[Override]
    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    #[Override]
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    #[Override]
    public function setDomain(?string $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    #[Override]
    public function getCreatedById(): ?Ulid
    {
        return $this->createdById;
    }

    #[Override]
    public function setCreatedById(?Ulid $createdById): static
    {
        $this->createdById = $createdById;

        return $this;
    }

    #[Override]
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
