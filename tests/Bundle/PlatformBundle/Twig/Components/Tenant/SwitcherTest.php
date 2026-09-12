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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Twig\Components\Tenant;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Entity\User;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantChoice;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantLock;
use SolidWorx\Platform\PlatformBundle\Twig\Components\Tenant\Switcher;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Ulid;

#[CoversClass(Switcher::class)]
#[CoversClass(TenantChoice::class)]
#[UsesClass(TenantContext::class)]
#[UsesClass(TenantLock::class)]
final class SwitcherTest extends TestCase
{
    public function testOffersAChoiceBetweenSeveralWorkspaces(): void
    {
        $switcher = $this->createSwitcher([
            new TenantChoice(new Ulid(), 'Acme'),
            new TenantChoice(new Ulid(), 'Globex'),
        ]);

        $this->assertTrue($switcher->isAvailable());
        $this->assertCount(2, $switcher->getTenants());
    }

    /**
     * One workspace is not a choice, so the switcher stays out of the way rather than rendering a
     * menu with a single entry.
     */
    public function testRendersNothingWithASingleWorkspace(): void
    {
        $switcher = $this->createSwitcher([new TenantChoice(new Ulid(), 'Acme')]);

        $this->assertFalse($switcher->isAvailable());
    }

    public function testRendersNothingForAnAnonymousVisitor(): void
    {
        $switcher = $this->createSwitcher([new TenantChoice(new Ulid(), 'Acme')], anonymous: true);

        $this->assertFalse($switcher->isAvailable());
        $this->assertSame([], $switcher->getTenants());
    }

    /**
     * On a custom domain the workspace is fixed by the host, so there is nothing to switch to.
     */
    public function testRendersNothingWhileTheTenantIsLocked(): void
    {
        $switcher = $this->createSwitcher(
            [new TenantChoice(new Ulid(), 'Acme'), new TenantChoice(new Ulid(), 'Globex')],
            locked: true,
        );

        $this->assertFalse($switcher->isAvailable());
    }

    public function testMarksTheWorkspaceInScope(): void
    {
        $current = new TenantChoice(new Ulid(), 'Acme');
        $other = new TenantChoice(new Ulid(), 'Globex');

        $switcher = $this->createSwitcher([$current, $other], currentTenantId: $current->id);

        $this->assertSame($current, $switcher->getCurrent());
    }

    public function testHasNoCurrentWorkspaceWithoutOneInScope(): void
    {
        $switcher = $this->createSwitcher([new TenantChoice(new Ulid(), 'Acme')]);

        $this->assertNull($switcher->getCurrent());
    }

    /**
     * @param list<TenantChoice> $tenants
     */
    private function createSwitcher(
        array $tenants,
        bool $locked = false,
        ?Ulid $currentTenantId = null,
        bool $anonymous = false,
    ): Switcher {
        $user = null;

        if (! $anonymous) {
            $user = self::createStub(User::class);
            $user->method('getId')->willReturn(new Ulid());
        }

        $security = self::createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $repository = self::createStub(UserTenantRepository::class);
        $repository->method('findTenantsForUser')->willReturn($tenants);

        $context = new TenantContext(new EventDispatcher());

        if ($currentTenantId instanceof Ulid) {
            $context->setTenant($currentTenantId);
        }

        $lock = new TenantLock();

        if ($locked) {
            $lock->lock(new Ulid());
        }

        return new Switcher($security, $repository, $context, $lock);
    }
}
