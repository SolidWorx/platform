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
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\SaasBundle\Entity\Subscription;
use SolidWorx\Platform\SaasBundle\Entity\Trial;
use SolidWorx\Platform\SaasBundle\Exception\TrialAlreadyExistsException;
use SolidWorx\Platform\SaasBundle\Repository\TrialRepositoryInterface;
use SolidWorx\Platform\SaasBundle\Trial\TrialManager;
use SolidWorx\Platform\SaasBundle\Trial\TrialUserInterface;
use Symfony\Component\Uid\Ulid;

#[CoversClass(TrialManager::class)]
#[UsesClass(Trial::class)]
#[UsesClass(TrialAlreadyExistsException::class)]
final class TrialManagerTest extends TestCase
{
    private TrialRepositoryInterface & MockObject $trialRepository;

    protected function setUp(): void
    {
        $this->trialRepository = $this->createMock(TrialRepositoryInterface::class);
    }

    public function testUserHasTrialDelegatesToRepository(): void
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $manager = new TrialManager($this->trialRepository, $entityManager);

        $user = $this->mockUser();

        $this->trialRepository
            ->expects(self::once())
            ->method('userHasTrial')
            ->with($user)
            ->willReturn(true);

        self::assertTrue($manager->userHasTrial($user));
    }

    public function testUserHasTrialReturnsFalseWhenNoTrial(): void
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $manager = new TrialManager($this->trialRepository, $entityManager);

        $user = $this->mockUser();

        $this->trialRepository
            ->expects(self::once())
            ->method('userHasTrial')
            ->with($user)
            ->willReturn(false);

        self::assertFalse($manager->userHasTrial($user));
    }

    public function testCreateTrialDelegatesToRepositoryAndFlushes(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $manager = new TrialManager($this->trialRepository, $entityManager);

        $user = $this->mockUser();
        $subscription = self::createStub(Subscription::class);
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

        $entityManager
            ->expects(self::once())
            ->method('flush');

        self::assertSame($trial, $manager->createTrial($user, $subscription));
    }

    public function testCreateTrialThrowsWhenUserAlreadyHasTrial(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $manager = new TrialManager($this->trialRepository, $entityManager);

        $user = $this->mockUser();
        $subscription = self::createStub(Subscription::class);

        $this->trialRepository
            ->expects(self::once())
            ->method('userHasTrial')
            ->with($user)
            ->willReturn(true);

        $this->trialRepository
            ->expects(self::never())
            ->method('createTrial');

        $entityManager
            ->expects(self::never())
            ->method('flush');

        $this->expectException(TrialAlreadyExistsException::class);

        $manager->createTrial($user, $subscription);
    }

    private function mockUser(): TrialUserInterface & Stub
    {
        $user = self::createStub(TrialUserInterface::class);
        $user->method('getId')->willReturn(new Ulid());

        return $user;
    }
}
