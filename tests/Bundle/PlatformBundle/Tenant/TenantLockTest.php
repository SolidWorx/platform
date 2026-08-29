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
use SolidWorx\Platform\PlatformBundle\Tenant\TenantLock;
use Symfony\Component\Uid\Ulid;

#[CoversClass(TenantLock::class)]
final class TenantLockTest extends TestCase
{
    public function testIsUnlockedByDefault(): void
    {
        $lock = new TenantLock();

        $this->assertFalse($lock->isLocked());
        $this->assertNull($lock->getTenantId());
        $this->assertFalse($lock->forbidsSwitchTo(new Ulid()));
    }

    public function testForbidsSwitchingToAnotherTenant(): void
    {
        $lock = new TenantLock();
        $lock->lock(new Ulid());

        $this->assertTrue($lock->isLocked());
        $this->assertTrue($lock->forbidsSwitchTo(new Ulid()));
    }

    public function testAllowsReapplyingTheLockedTenant(): void
    {
        $tenantId = new Ulid();

        $lock = new TenantLock();
        $lock->lock($tenantId);

        $this->assertFalse($lock->forbidsSwitchTo($tenantId));
    }

    public function testForbidsClearingTheTenant(): void
    {
        $lock = new TenantLock();
        $lock->lock(new Ulid());

        $this->assertTrue($lock->forbidsSwitchTo(null));
    }

    public function testResetReleasesTheLock(): void
    {
        $lock = new TenantLock();
        $lock->lock(new Ulid());

        $lock->reset();

        $this->assertFalse($lock->isLocked());
    }
}
