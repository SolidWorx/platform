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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Tenant;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Exception\TenantAccessDeniedException;
use SolidWorx\Platform\PlatformBundle\Tenant\Event\TenantSwitchedEvent;
use SolidWorx\Platform\PlatformBundle\Tenant\Resolver\LockingTenantResolverInterface;
use SolidWorx\Platform\PlatformBundle\Tenant\Resolver\TenantResolverInterface;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantLock;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantRequestListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Uid\Ulid;

#[CoversClass(TenantRequestListener::class)]
#[UsesClass(TenantContext::class)]
#[UsesClass(TenantLock::class)]
#[UsesClass(TenantSwitchedEvent::class)]
final class TenantRequestListenerTest extends TestCase
{
    private TenantContext $context;

    private TenantLock $lock;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->context = new TenantContext($this->eventDispatcher);
        $this->lock = new TenantLock();
    }

    public function testAppliesTheFirstResolvedTenant(): void
    {
        $first = new Ulid();
        $second = new Ulid();

        $this->listener([$this->resolver(null), $this->resolver($first), $this->resolver($second)])(
            $this->createEvent(),
        );

        $this->assertSame($first->toRfc4122(), $this->context->getTenantId()?->toRfc4122());
    }

    public function testDoesNotLockForAnOrdinaryResolver(): void
    {
        $this->listener([$this->resolver(new Ulid())])($this->createEvent());

        $this->assertFalse($this->lock->isLocked());
    }

    public function testLocksForALockingResolver(): void
    {
        $tenantId = new Ulid();

        $this->listener([$this->lockingResolver($tenantId)])($this->createEvent());

        $this->assertTrue($this->lock->isLocked());
        $this->assertSame($tenantId->toRfc4122(), $this->lock->getTenantId()?->toRfc4122());
    }

    /**
     * A switch the access-validation listener vetoes must not leave a lock behind — otherwise a
     * rejected tenant would still pin the request.
     */
    public function testDoesNotLockWhenTheSwitchIsVetoed(): void
    {
        $this->eventDispatcher->addListener(
            TenantSwitchedEvent::class,
            static function (): void {
                throw new TenantAccessDeniedException();
            },
        );

        $listener = $this->listener([$this->lockingResolver(new Ulid())]);

        try {
            $listener($this->createEvent());
        } catch (TenantAccessDeniedException) {
            // Expected: the veto propagates as a 403.
        }

        $this->assertFalse($this->lock->isLocked());
    }

    public function testIgnoresSubRequests(): void
    {
        $this->listener([$this->resolver(new Ulid())])(
            $this->createEvent(HttpKernelInterface::SUB_REQUEST),
        );

        $this->assertFalse($this->context->hasTenant());
    }

    public function testLeavesTheContextAloneWhenNothingResolves(): void
    {
        $this->listener([$this->resolver(null)])($this->createEvent());

        $this->assertFalse($this->context->hasTenant());
        $this->assertFalse($this->lock->isLocked());
    }

    /**
     * @param list<TenantResolverInterface> $resolvers
     */
    private function listener(array $resolvers): TenantRequestListener
    {
        return new TenantRequestListener($resolvers, $this->context, $this->lock);
    }

    private function resolver(?Ulid $tenantId): TenantResolverInterface
    {
        $resolver = self::createStub(TenantResolverInterface::class);
        $resolver->method('resolve')->willReturn($tenantId);

        return $resolver;
    }

    private function lockingResolver(?Ulid $tenantId): LockingTenantResolverInterface
    {
        $resolver = self::createStub(LockingTenantResolverInterface::class);
        $resolver->method('resolve')->willReturn($tenantId);

        return $resolver;
    }

    private function createEvent(int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent(
            self::createStub(HttpKernelInterface::class),
            Request::create('https://acme.example.com/'),
            $requestType,
        );
    }
}
