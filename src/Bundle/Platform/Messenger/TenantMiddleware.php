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

namespace SolidWorx\Platform\PlatformBundle\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantManager;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Ulid;

/**
 * Propagates the tenant in scope across the message bus.
 *
 * On dispatch, the current tenant is recorded on a {@see TenantStamp} (and on the message body when
 * it implements {@see TenantAwareMessageInterface}). On handling in a worker, the tenant is
 * restored for the duration of the handler and cleared afterwards, so messages are always processed
 * in their originating tenant — including Scheduler-dispatched messages.
 */
final readonly class TenantMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TenantContext $tenantContext,
        private TenantManager $tenantManager,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (!$envelope->last(ReceivedStamp::class) instanceof StampInterface) {
            return $stack->next()->handle($this->stampOnSend($envelope), $stack);
        }

        $tenantId = $this->resolveIncomingTenant($envelope);

        if (!$tenantId instanceof Ulid) {
            return $stack->next()->handle($envelope, $stack);
        }

        return $this->tenantManager->runAs($tenantId, static fn (): Envelope => $stack->next()->handle($envelope, $stack));
    }

    private function stampOnSend(Envelope $envelope): Envelope
    {
        $message = $envelope->getMessage();

        if ($message instanceof TenantAwareMessageInterface && !$message->getTenantId() instanceof Ulid && $this->tenantContext->hasTenant()) {
            $message->setTenantId($this->tenantContext->getTenantId());
        }

        if ($envelope->last(TenantStamp::class) instanceof StampInterface) {
            return $envelope;
        }

        $tenantId = $message instanceof TenantAwareMessageInterface
            ? $message->getTenantId()
            : $this->tenantContext->getTenantId();

        if (!$tenantId instanceof Ulid) {
            return $envelope;
        }

        return $envelope->with(new TenantStamp($tenantId));
    }

    private function resolveIncomingTenant(Envelope $envelope): ?Ulid
    {
        $message = $envelope->getMessage();

        if ($message instanceof TenantAwareMessageInterface && $message->getTenantId() instanceof Ulid) {
            return $message->getTenantId();
        }

        return $envelope->last(TenantStamp::class)?->getTenantId();
    }
}
