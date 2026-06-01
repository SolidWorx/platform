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
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SolidWorx\Platform\PlatformBundle\Tenant\Event\TenantSwitchedEvent;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use SolidWorx\Platform\Test\Traits\InteractsWithTenantsTrait;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Ulid;

#[CoversClass(TenantContext::class)]
#[CoversClass(TenantSwitchedEvent::class)]
final class TenantContextTest extends TestCase
{
    use InteractsWithTenantsTrait;

    public function testSetGetClear(): void
    {
        $context = $this->createTenantContext();
        $tenantId = new Ulid();

        $this->assertFalse($context->hasTenant());
        $this->assertNotInstanceOf(Ulid::class, $context->getTenantId());

        $context->setTenant($tenantId);

        $this->assertTrue($context->hasTenant());
        $this->assertSame($tenantId->toRfc4122(), $context->getTenantId()?->toRfc4122());

        $context->clear();

        $this->assertFalse($context->hasTenant());
        $this->assertNotInstanceOf(Ulid::class, $context->getTenantId());
    }

    public function testNormalizesTenantEntity(): void
    {
        $context = $this->createTenantContext();
        $tenant = $this->createTenant();

        $context->setTenant($tenant);

        $this->assertSame($tenant->getId()->toRfc4122(), $context->getTenantId()?->toRfc4122());
    }

    public function testDispatchesEventOnlyWhenChanged(): void
    {
        $dispatcher = new EventDispatcher();
        $events = [];
        $dispatcher->addListener(TenantSwitchedEvent::class, static function (TenantSwitchedEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $context = new TenantContext($dispatcher);
        $tenantId = new Ulid();

        $context->setTenant($tenantId);
        $context->setTenant($tenantId);

        $this->assertCount(1, $events);
        $this->assertNotInstanceOf(Ulid::class, $events[0]->getPreviousTenantId());
        $this->assertSame($tenantId->toRfc4122(), $events[0]->getTenantId()?->toRfc4122());
    }

    public function testDoesNotCommitWhenListenerVetoes(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(TenantSwitchedEvent::class, static function (): never {
            throw new RuntimeException('vetoed');
        });

        $context = new TenantContext($dispatcher);

        try {
            $context->setTenant(new Ulid());
        } catch (RuntimeException) {
            // expected
        }

        $this->assertFalse($context->hasTenant());
    }

    public function testPushPopRestoresPreviousTenant(): void
    {
        $context = $this->createTenantContext();
        $first = new Ulid();
        $second = new Ulid();

        $context->setTenant($first);
        $context->push($second);

        $this->assertSame($second->toRfc4122(), $context->getTenantId()?->toRfc4122());

        $context->pop();

        $this->assertSame($first->toRfc4122(), $context->getTenantId()?->toRfc4122());
    }

    public function testResetClearsState(): void
    {
        $context = $this->createTenantContext();
        $context->setTenant(new Ulid());

        $context->reset();

        $this->assertFalse($context->hasTenant());
    }
}
