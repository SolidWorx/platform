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
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Entity\User;
use SolidWorx\Platform\PlatformBundle\Security\Voter\TenantCreationVoter;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantLock;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Uid\Ulid;

#[CoversClass(TenantCreationVoter::class)]
#[UsesClass(TenantLock::class)]
final class TenantCreationVoterTest extends TestCase
{
    public function testGrantsCreationToASignedInUser(): void
    {
        $vote = new Vote();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($vote));
        self::assertSame([], $vote->reasons);
    }

    public function testAbstainsFromEveryOtherAttribute(): void
    {
        $voter = new TenantCreationVoter(new TenantLock(), onboardingEnabled: true);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($this->token(), null, ['TENANT_ACCESS']));
    }

    public function testRefusesWhenOnboardingIsDisabled(): void
    {
        $vote = new Vote();

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($vote, onboardingEnabled: false));
        self::assertSame(['Creating workspaces is disabled.'], $vote->reasons);
    }

    /**
     * On a custom domain the workspace is fixed by the host, so a new one could not be entered.
     */
    public function testRefusesWhileTheTenantIsLocked(): void
    {
        $lock = new TenantLock();
        $lock->lock(new Ulid());

        $vote = new Vote();

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($vote, lock: $lock));
        self::assertSame(['The workspace is fixed by the domain and cannot be changed.'], $vote->reasons);
    }

    public function testRefusesAnAnonymousVisitor(): void
    {
        $vote = new Vote();

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($vote, anonymous: true));
        self::assertSame(['Only a signed-in user can create a workspace.'], $vote->reasons);
    }

    private function vote(Vote $vote, bool $onboardingEnabled = true, ?TenantLock $lock = null, bool $anonymous = false): int
    {
        $voter = new TenantCreationVoter($lock ?? new TenantLock(), $onboardingEnabled);

        return $voter->vote($this->token($anonymous), null, [TenantCreationVoter::TENANT_CREATE], $vote);
    }

    private function token(bool $anonymous = false): TokenInterface
    {
        $user = null;

        if (! $anonymous) {
            $user = self::createStub(User::class);
            $user->method('getId')->willReturn(new Ulid());
        }

        $token = self::createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
