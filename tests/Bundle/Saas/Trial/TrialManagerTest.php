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

namespace SolidWorx\Platform\Tests\Bundle\Saas\Trial;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Entity\Trial;
use SolidWorx\Platform\SaasBundle\Exception\TrialAlreadyExistsException;
use SolidWorx\Platform\SaasBundle\Repository\TrialRepositoryInterface;
use SolidWorx\Platform\SaasBundle\Trial\TrialManager;
use SolidWorx\Platform\SaasBundle\Trial\TrialUserInterface;
use Symfony\Component\Uid\Ulid;

#[CoversClass(TrialManager::class)]
final class TrialManagerTest extends TestCase
{
    private TrialRepositoryInterface&MockObject $trialRepository;

    private EntityManagerInterface&MockObject $entityManager;

    private TrialManager $manager;

    protected function setUp(): void
    {
        $this->trialRepository = $this->createMock(TrialRepositoryInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->manager = new TrialManager($this->trialRepository, $this->entityManager);
    }

    public function testUserHasTrialDelegatesToRepository(): void
    {
        $user = $this->mockUser();

        $this->trialRepository
            ->expects(self::once())
            ->method('userHasTrial')
            ->with($user)
            ->willReturn(true);

        self::assertTrue($this->manager->userHasTrial($user));
    }

    public function testUserHasTrialReturnsFalseWhenNoTrial(): void
    {
        $user = $this->mockUser();

        $this->trialRepository
            ->expects(self::once())
            ->method('userHasTrial')
            ->with($user)
            ->willReturn(false);

        self::assertFalse($this->manager->userHasTrial($user));
    }

    public function testCreateTrialDelegatesToRepositoryAndFlushes(): void
    {
        $user = $this->mockUser();
        $subscription = $this->createMock(Subscription::class);
        $trial = Trial::create($user, $subscription);

        $this->trialRepository
            ->expects(self::once())
            ->method('userHasTrial')
            ->with($user)
            ->willReturn(false);

        $this->trialRepository
            ->expects(self::once())
            ->method('createTrial')
            ->with($user, $subscription)
            ->willReturn($trial);

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        self::assertSame($trial, $this->manager->createTrial($user, $subscription));
    }

    public function testCreateTrialThrowsWhenUserAlreadyHasTrial(): void
    {
        $user = $this->mockUser();
        $subscription = $this->createMock(Subscription::class);

        $this->trialRepository
            ->expects(self::once())
            ->method('userHasTrial')
            ->with($user)
            ->willReturn(true);

        $this->trialRepository
            ->expects(self::never())
            ->method('createTrial');

        $this->entityManager
            ->expects(self::never())
            ->method('flush');

        $this->expectException(TrialAlreadyExistsException::class);

        $this->manager->createTrial($user, $subscription);
    }

    private function mockUser(): TrialUserInterface&MockObject
    {
        $user = $this->createMock(TrialUserInterface::class);
        $user->method('getId')->willReturn(new Ulid());

        return $user;
    }
}
