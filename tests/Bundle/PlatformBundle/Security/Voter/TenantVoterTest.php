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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Security\Voter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Entity\Tenant;
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use SolidWorx\Platform\PlatformBundle\Security\Voter\TenantVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Uid\Ulid;

#[CoversClass(TenantVoter::class)]
final class TenantVoterTest extends TestCase
{
    public function testGrantsForMember(): void
    {
        $vote = $this->vote($this->userWithId(), true);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $vote);
    }

    public function testDeniesForNonMember(): void
    {
        $vote = $this->vote($this->userWithId(), false);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }

    public function testDeniesWhenNoUser(): void
    {
        $vote = $this->vote(null, true);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }

    private function userWithId(): User
    {
        $user = self::createStub(User::class);
        $user->method('getId')->willReturn(new Ulid());

        return $user;
    }

    private function vote(?User $user, bool $hasAccess): int
    {
        $repository = self::createStub(UserTenantRepository::class);
        $repository->method('hasAccess')->willReturn($hasAccess);

        $token = self::createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $voter = new TenantVoter($repository);

        return $voter->vote($token, new Tenant('Acme'), [TenantVoter::TENANT_ACCESS]);
    }
}
