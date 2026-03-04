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
use SolidWorx\Platform\PlatformBundle\Enum\WebhookEventStatus;
use SolidWorx\Platform\PlatformBundle\Repository\WebhookEventLogRepository;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\NilUlid;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: WebhookEventLogRepository::class)]
#[ORM\Table(name: WebhookEventLog::TABLE_NAME)]
#[ORM\Index(columns: ['gateway'], name: 'idx_webhook_event_log_gateway')]
#[ORM\Index(columns: ['gateway', 'event_type'], name: 'idx_webhook_event_log_gateway_event_type')]
#[ORM\Index(columns: ['status'], name: 'idx_webhook_event_log_status')]
#[ORM\Index(columns: ['gateway_event_id'], name: 'idx_webhook_event_log_gateway_event_id')]
#[ORM\Index(columns: ['external_subscription_id'], name: 'idx_webhook_event_log_external_subscription_id')]
#[ORM\Index(columns: ['received_at'], name: 'idx_webhook_event_log_received_at')]
class WebhookEventLog
{
    public const string TABLE_NAME = 'webhook_event_log';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    private Ulid $id;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $gateway;

    #[ORM\Column(name: 'event_type', type: Types::STRING, length: 100, nullable: true)]
    private ?string $eventType = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: WebhookEventStatus::class)]
    private WebhookEventStatus $status = WebhookEventStatus::RECEIVED;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    /**
     * @var array<string, string>
     */
    #[ORM\Column(name: 'request_headers', type: Types::JSON)]
    private array $requestHeaders = [];

    #[ORM\Column(name: 'gateway_event_id', type: Types::STRING, length: 128, nullable: true)]
    private ?string $gatewayEventId = null;

    #[ORM\Column(name: 'external_subscription_id', type: Types::STRING, length: 128, nullable: true)]
    private ?string $externalSubscriptionId = null;

    #[ORM\Column(name: 'received_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeImmutable $receivedAt;

    #[ORM\Column(name: 'processed_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $processedAt = null;

    #[ORM\Column(name: 'error_message', type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    public function __construct()
    {
        $this->id = new NilUlid();
        $this->receivedAt = new DateTimeImmutable();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getGateway(): string
    {
        return $this->gateway;
    }

    public function setGateway(string $gateway): static
    {
        $this->gateway = $gateway;

        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(?string $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getStatus(): WebhookEventStatus
    {
        return $this->status;
    }

    public function setStatus(WebhookEventStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getRequestHeaders(): array
    {
        return $this->requestHeaders;
    }

    /**
     * @param array<string, string> $requestHeaders
     */
    public function setRequestHeaders(array $requestHeaders): static
    {
        $this->requestHeaders = $requestHeaders;

        return $this;
    }

    public function getGatewayEventId(): ?string
    {
        return $this->gatewayEventId;
    }

    public function setGatewayEventId(?string $gatewayEventId): static
    {
        $this->gatewayEventId = $gatewayEventId;

        return $this;
    }

    public function getExternalSubscriptionId(): ?string
    {
        return $this->externalSubscriptionId;
    }

    public function setExternalSubscriptionId(?string $externalSubscriptionId): static
    {
        $this->externalSubscriptionId = $externalSubscriptionId;

        return $this;
    }

    public function getReceivedAt(): DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function getProcessedAt(): ?DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }
}
