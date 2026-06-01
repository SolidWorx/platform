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
use SolidWorx\Platform\PlatformBundle\Repository\TenantRepository;
use Stringable;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\NilUlid;
use Symfony\Component\Uid\Ulid;

/**
 * A tenant represents an isolated boundary that owns tenant-aware data.
 */
#[ORM\Entity(repositoryClass: TenantRepository::class)]
#[ORM\Table(name: Tenant::TABLE_NAME)]
#[ORM\UniqueConstraint(name: 'uniq_tenant_domain', fields: ['domain'])]
class Tenant implements Stringable
{
    final public const string TABLE_NAME = 'platform_tenant';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    private Ulid $id;

    /**
     * The custom hostname mapped to this tenant, used for domain-based resolution.
     */
    #[ORM\Column(name: 'domain', type: Types::STRING, length: 255, unique: true, nullable: true)]
    private ?string $domain = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(#[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private string $name, #[ORM\Column(name: 'created_by_id', type: UlidType::NAME, nullable: true)]
    private ?Ulid $createdById = null)
    {
        $this->id = new NilUlid();
        $this->createdAt = new DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name;
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

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(?string $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    public function getCreatedById(): ?Ulid
    {
        return $this->createdById;
    }

    public function setCreatedById(?Ulid $createdById): static
    {
        $this->createdById = $createdById;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
