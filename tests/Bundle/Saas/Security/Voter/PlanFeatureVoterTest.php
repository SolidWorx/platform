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

namespace SolidWorx\Platform\Tests\Bundle\Saas\Security\Voter;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\SaasBundle\Feature\PlanFeatureManager;
use SolidWorx\Platform\SaasBundle\Security\Voter\PlanFeatureVoter;
use SolidWorx\Platform\SaasBundle\Subscriber\SubscribableInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

#[CoversClass(PlanFeatureVoter::class)]
final class PlanFeatureVoterTest extends TestCase
{
    private PlanFeatureManager&MockObject $planFeatureManager;

    private PlanFeatureVoter $voter;

    #[Override]
    protected function setUp(): void
    {
        $this->planFeatureManager = $this->createMock(PlanFeatureManager::class);
        $this->voter = new PlanFeatureVoter($this->planFeatureManager);
    }

    #[DataProvider('supportsProvider')]
    public function testSupports(string $attribute, mixed $subject, bool $expected): void
    {
        $token = $this->createMock(TokenInterface::class);

        $result = $this->voter->vote($token, $subject, [$attribute]);

        if ($expected) {
            $this->assertNotSame(VoterInterface::ACCESS_ABSTAIN, $result);
        } else {
            $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
        }
    }

    /**
     * @return iterable<string, array{string, mixed, bool}>
     */
    public static function supportsProvider(): iterable
    {
        $subscriber = self::createMockSubscriber();

        yield 'supports FEATURE_ prefix with subscriber' => ['FEATURE_API_ACCESS', $subscriber, true];
        yield 'supports FEATURE_ prefix with array subject' => [
            'FEATURE_MAX_USERS',
            [
                'subscriber' => $subscriber,
                'usage' => 5,
            ],
            true,
        ];
        yield 'does not support non-FEATURE prefix' => ['ROLE_ADMIN', $subscriber, false];
        yield 'does not support invalid subject' => ['FEATURE_API_ACCESS', 'invalid', false];
        yield 'does not support null subject' => ['FEATURE_API_ACCESS', null, false];
    }

    public function testVoteGrantsAccessWhenFeatureEnabled(): void
    {
        $subscriber = $this->createMock(SubscribableInterface::class);
        $token = $this->createMock(TokenInterface::class);

        $this->planFeatureManager
            ->expects($this->once())
            ->method('hasFeatureForSubscriber')
            ->with($subscriber, 'api_access')
            ->willReturn(true);

        $result = $this->voter->vote($token, $subscriber, ['FEATURE_API_ACCESS']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testVoteDeniesAccessWhenFeatureDisabled(): void
    {
        $subscriber = $this->createMock(SubscribableInterface::class);
        $token = $this->createMock(TokenInterface::class);

        $this->planFeatureManager
            ->expects($this->once())
            ->method('hasFeatureForSubscriber')
            ->with($subscriber, 'api_access')
            ->willReturn(false);

        $result = $this->voter->vote($token, $subscriber, ['FEATURE_API_ACCESS']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testVoteGrantsAccessWithinUsageLimit(): void
    {
        $subscriber = $this->createMock(SubscribableInterface::class);
        $token = $this->createMock(TokenInterface::class);

        $this->planFeatureManager
            ->expects($this->once())
            ->method('canUseForSubscriber')
            ->with($subscriber, 'max_users', 5)
            ->willReturn(true);

        $result = $this->voter->vote($token, [
            'subscriber' => $subscriber,
            'usage' => 5,
        ], ['FEATURE_MAX_USERS']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testVoteDeniesAccessWhenUsageLimitExceeded(): void
    {
        $subscriber = $this->createMock(SubscribableInterface::class);
        $token = $this->createMock(TokenInterface::class);

        $this->planFeatureManager
            ->expects($this->once())
            ->method('canUseForSubscriber')
            ->with($subscriber, 'max_users', 15)
            ->willReturn(false);

        $result = $this->voter->vote($token, [
            'subscriber' => $subscriber,
            'usage' => 15,
        ], ['FEATURE_MAX_USERS']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testVoteWithArraySubjectDefaultsToZeroUsage(): void
    {
        $subscriber = $this->createMock(SubscribableInterface::class);
        $token = $this->createMock(TokenInterface::class);

        $this->planFeatureManager
            ->expects($this->once())
            ->method('canUseForSubscriber')
            ->with($subscriber, 'max_users', 0)
            ->willReturn(true);

        $result = $this->voter->vote($token, [
            'subscriber' => $subscriber,
        ], ['FEATURE_MAX_USERS']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testFeatureKeyIsLowercased(): void
    {
        $subscriber = $this->createMock(SubscribableInterface::class);
        $token = $this->createMock(TokenInterface::class);

        $this->planFeatureManager
            ->expects($this->once())
            ->method('hasFeatureForSubscriber')
            ->with($subscriber, 'api_access')
            ->willReturn(true);

        $this->voter->vote($token, $subscriber, ['FEATURE_API_ACCESS']);
    }

    private static function createMockSubscriber(): SubscribableInterface
    {
        return new class() implements SubscribableInterface {
        };
    }
}
