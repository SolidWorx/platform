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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Entity\User;
use SolidWorx\Platform\PlatformBundle\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

#[CoversClass(UserRepository::class)]
final class UserRepositoryTest extends TestCase
{
    /**
     * Disabling an account must end the sessions that are already open, not only block new logins.
     */
    public function testRefreshingADisabledUserLogsThemOut(): void
    {
        $user = new User()->setEnabled(true);
        $repository = $this->createRepository(new User()->setEnabled(false));

        $this->expectException(UserNotFoundException::class);

        $repository->refreshUser($user);
    }

    public function testRefreshingAnEnabledUserReturnsTheStoredUser(): void
    {
        $stored = new User()->setEnabled(true);

        self::assertSame($stored, $this->createRepository($stored)->refreshUser(new User()->setEnabled(true)));
    }

    public function testRefreshingADeletedUserLogsThemOut(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->createRepository(null)->refreshUser(new User());
    }

    /**
     * @return UserRepository<User>
     */
    private function createRepository(?User $stored): UserRepository
    {
        $repository = $this->createPartialMock(UserRepository::class, ['find', 'getEntityName']);
        $repository->method('getEntityName')->willReturn(User::class);
        $repository->expects($this->once())->method('find')->willReturn($stored);

        return $repository;
    }
}
