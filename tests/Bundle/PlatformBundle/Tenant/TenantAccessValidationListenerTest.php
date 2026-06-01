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
use SolidWorx\Platform\PlatformBundle\Exception\TenantAccessDeniedException;
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use SolidWorx\Platform\PlatformBundle\Tenant\Event\TenantSwitchedEvent;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantAccessValidationListener;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Ulid;

#[CoversClass(TenantAccessValidationListener::class)]
#[CoversClass(TenantAccessDeniedException::class)]
final class TenantAccessValidationListenerTest extends TestCase
{
    public function testDeniesNonMember(): void
    {
        $listener = $this->createListener($this->userWithId(), false, true);

        $this->expectException(TenantAccessDeniedException::class);

        $listener(new TenantSwitchedEvent(null, new Ulid()));
    }

    public function testAllowsMember(): void
    {
        $listener = $this->createListener($this->userWithId(), true, true);

        $listener(new TenantSwitchedEvent(null, new Ulid()));

        $this->expectNotToPerformAssertions();
    }

    public function testSkipsWhenNoUser(): void
    {
        $listener = $this->createListener(null, false, true);

        $listener(new TenantSwitchedEvent(null, new Ulid()));

        $this->expectNotToPerformAssertions();
    }

    public function testSkipsWhenValidationDisabled(): void
    {
        $listener = $this->createListener($this->userWithId(), false, false);

        $listener(new TenantSwitchedEvent(null, new Ulid()));

        $this->expectNotToPerformAssertions();
    }

    public function testSkipsWhenClearingTenant(): void
    {
        $listener = $this->createListener($this->userWithId(), false, true);

        $listener(new TenantSwitchedEvent(new Ulid(), null));

        $this->expectNotToPerformAssertions();
    }

    private function userWithId(): User
    {
        $user = self::createStub(User::class);
        $user->method('getId')->willReturn(new Ulid());

        return $user;
    }

    private function createListener(?UserInterface $user, bool $hasAccess, bool $validate): TenantAccessValidationListener
    {
        $security = self::createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $repository = self::createStub(UserTenantRepository::class);
        $repository->method('hasAccess')->willReturn($hasAccess);

        return new TenantAccessValidationListener($security, $repository, $validate);
    }
}
