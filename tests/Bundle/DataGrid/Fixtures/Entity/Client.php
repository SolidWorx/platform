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

namespace SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Client
{
    #[ORM\Id]
    #[ORM\Column]
    private int $id = 0;

    #[ORM\Column]
    private string $companyName = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $notes = '';

    #[ORM\Column]
    private bool $active = false;

    #[ORM\Column]
    private int $seatCount = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $monthlyFee = '0.00';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /**
     * Deliberately has no getter, so it must be skipped.
     */
    #[ORM\Column]
    private string $internalToken = '';

    #[ORM\ManyToOne(targetEntity: Country::class)]
    private ?Country $country = null;

    #[ORM\ManyToOne(targetEntity: Tag::class)]
    private ?Tag $primaryTag = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    private Collection $tags;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->tags = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getSeatCount(): int
    {
        return $this->seatCount;
    }

    public function getMonthlyFee(): string
    {
        return $this->monthlyFee;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function getPrimaryTag(): ?Tag
    {
        return $this->primaryTag;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }
}
