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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Messenger;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Messenger\TenantMiddleware;
use SolidWorx\Platform\PlatformBundle\Messenger\TenantStamp;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantManager;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Message\TenantAwareMessage;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Ulid;
use stdClass;

#[CoversClass(TenantMiddleware::class)]
#[CoversClass(TenantStamp::class)]
final class TenantMiddlewareTest extends TestCase
{
    public function testStampsTenantOnSend(): void
    {
        $tenantId = new Ulid();
        $context = new TenantContext(new EventDispatcher());
        $context->setTenant($tenantId);

        $middleware = $this->middleware($context);

        $envelope = $middleware->handle(new Envelope(new stdClass()), $this->passThroughStack());

        $stamp = $envelope->last(TenantStamp::class);

        $this->assertInstanceOf(TenantStamp::class, $stamp);
        $this->assertSame($tenantId->toRfc4122(), $stamp->getTenantId()->toRfc4122());
    }

    public function testStampsTenantOnAwareMessage(): void
    {
        $tenantId = new Ulid();
        $context = new TenantContext(new EventDispatcher());
        $context->setTenant($tenantId);

        $middleware = $this->middleware($context);

        $message = new TenantAwareMessage();
        $envelope = $middleware->handle(new Envelope($message), $this->passThroughStack());

        $this->assertSame($tenantId->toRfc4122(), $message->getTenantId()?->toRfc4122());
        $this->assertInstanceOf(TenantStamp::class, $envelope->last(TenantStamp::class));
    }

    public function testRestoresTenantWhileHandlingAndClearsAfter(): void
    {
        $tenantId = new Ulid();
        $context = new TenantContext(new EventDispatcher());

        $middleware = $this->middleware($context);

        $seen = null;
        $stack = $this->capturingStack(function () use ($context, &$seen): void {
            $seen = $context->getTenantId();
        });

        $envelope = new Envelope(new stdClass(), [
            new ReceivedStamp('async'),
            new TenantStamp($tenantId),
        ]);

        $middleware->handle($envelope, $stack);

        $this->assertSame($tenantId->toRfc4122(), $seen?->toRfc4122());
        $this->assertFalse($context->hasTenant());
    }

    private function middleware(TenantContext $context): TenantMiddleware
    {
        return new TenantMiddleware($context, new TenantManager($context, self::createStub(EntityManagerInterface::class)));
    }

    private function passThroughStack(): StackInterface
    {
        return $this->capturingStack(static function (): void {
        });
    }

    private function capturingStack(callable $onHandle): StackInterface
    {
        $next = new class($onHandle) implements MiddlewareInterface {
            /**
             * @param callable(Envelope): void $onHandle
             */
            public function __construct(private $onHandle)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                ($this->onHandle)($envelope);

                return $envelope;
            }
        };

        return new readonly class($next) implements StackInterface {
            public function __construct(private MiddlewareInterface $next)
            {
            }

            public function next(): MiddlewareInterface
            {
                return $this->next;
            }
        };
    }
}
