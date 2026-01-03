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

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SolidWorx\Platform\SaasBundle\Enum\FeatureType;
use SolidWorx\Platform\SaasBundle\Feature\FeatureValue;
use SolidWorx\Platform\SaasBundle\Repository\PlanFeatureRepository;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\NilUlid;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: PlanFeatureRepository::class)]
#[ORM\Table(name: PlanFeature::TABLE_NAME)]
#[ORM\UniqueConstraint(name: 'plan_feature_unique', fields: ['plan', 'featureKey'])]
#[ORM\Index(fields: ['plan'])]
#[ORM\Index(fields: ['featureKey'])]
class PlanFeature
{
    final public const string TABLE_NAME = 'saas_plan_feature';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Plan::class, inversedBy: 'features')]
    #[ORM\JoinColumn(name: 'plan_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Plan $plan;

    #[ORM\Column(name: 'feature_key', type: Types::STRING, length: 100)]
    private string $featureKey;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: FeatureType::class)]
    private FeatureType $type;

    /**
     * @var int|bool|string|array<mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private int|bool|string|array $value;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

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

    public function getFeatureKey(): string
    {
        return $this->featureKey;
    }

    public function setFeatureKey(string $featureKey): static
    {
        $this->featureKey = $featureKey;

        return $this;
    }

    public function getType(): FeatureType
    {
        return $this->type;
    }

    public function setType(FeatureType $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return int|bool|string|array<mixed>
     */
    public function getValue(): int|bool|string|array
    {
        return $this->value;
    }

    /**
     * @param int|bool|string|array<mixed> $value
     */
    public function setValue(int|bool|string|array $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function toFeatureValue(): FeatureValue
    {
        return new FeatureValue($this->featureKey, $this->type, $this->value);
    }
}
