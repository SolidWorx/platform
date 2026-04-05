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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SolidWorx\Platform\SaasBundle\Enum\SubscriptionLogType;
use SolidWorx\Platform\SaasBundle\Repository\SubscriptionLogRepository;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\NilUlid;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: SubscriptionLogRepository::class)]
#[ORM\Table(name: SubscriptionLog::TABLE_NAME)]
#[ORM\Index(columns: ['type'])]
#[ORM\Index(columns: ['subscription_id', 'created_at'], name: 'idx_subscription_log_subscription_created')]
class SubscriptionLog
{
    public const string TABLE_NAME = 'saas_subscription_log';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    private Ulid $id;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE, precision: 3)]
    private DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Subscription::class, inversedBy: 'logs')]
    #[ORM\JoinColumn(name: 'subscription_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Subscription $subscription;

    #[ORM\Column(type: Types::STRING, length: 45, enumType: SubscriptionLogType::class)]
    private SubscriptionLogType $type;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $comment = null;

    #[ORM\ManyToOne(targetEntity: WebhookEventLog::class)]
    #[ORM\JoinColumn(name: 'webhook_event_log_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?WebhookEventLog $webhookEventLog = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->id = new NilUlid();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getType(): SubscriptionLogType
    {
        return $this->type;
    }

    public function setType(SubscriptionLogType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getWebhookEventLog(): ?WebhookEventLog
    {
        return $this->webhookEventLog;
    }

    public function setWebhookEventLog(?WebhookEventLog $webhookEventLog): static
    {
        $this->webhookEventLog = $webhookEventLog;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }
}
