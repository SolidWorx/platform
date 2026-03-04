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

namespace SolidWorx\Platform\PlatformBundle\EventSubscriber;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use SolidWorx\Platform\PlatformBundle\Enum\WebhookEventStatus;
use SolidWorx\Platform\PlatformBundle\Model\WebhookEventLog;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

final readonly class WebhookRequestLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 0],
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
            KernelEvents::EXCEPTION => ['onKernelException', -1024],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->attributes->get('_route') !== '_webhook_controller') {
            return;
        }

        $gateway = $request->attributes->getString('type');
        $rawBody = $request->getContent();
        $decodedPayload = json_decode($rawBody, true);
        /** @var array<string, mixed> $payload */
        $payload = is_array($decodedPayload) ? $decodedPayload : [];

        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        $log = new WebhookEventLog();
        $log->setGateway($gateway);
        $log->setPayload($payload);
        $log->setRequestHeaders($headers);
        $log->setStatus(WebhookEventStatus::RECEIVED);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $request->attributes->set('_webhook_event_log', $log);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $log = $event->getRequest()->attributes->get('_webhook_event_log');

        if (! $log instanceof WebhookEventLog) {
            return;
        }

        $log->setStatus(WebhookEventStatus::PROCESSED);
        $log->setProcessedAt(new DateTimeImmutable());

        $this->entityManager->flush();
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $log = $event->getRequest()->attributes->get('_webhook_event_log');

        if (! $log instanceof WebhookEventLog) {
            return;
        }

        $throwable = $event->getThrowable();

        $log->setStatus(WebhookEventStatus::FAILED);
        $log->setErrorMessage($this->extractErrorMessage($throwable));
        $log->setProcessedAt(new DateTimeImmutable());

        $this->entityManager->flush();
    }

    private function extractErrorMessage(Throwable $throwable): string
    {
        return sprintf('[%s] %s', $throwable::class, $throwable->getMessage());
    }
}
