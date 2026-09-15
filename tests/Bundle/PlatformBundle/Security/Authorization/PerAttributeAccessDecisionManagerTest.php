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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Security\Authorization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Security\Authorization\PerAttributeAccessDecisionManager;
use stdClass;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

#[CoversClass(PerAttributeAccessDecisionManager::class)]
final class PerAttributeAccessDecisionManagerTest extends TestCase
{
    /**
     * The point of the whole class: under the default strategy the granting voter would win and the
     * application's refusal would never be counted.
     */
    public function testARefusalIsDecisiveForAnAttributeDecidedUnanimously(): void
    {
        $manager = $this->createManager();

        self::assertFalse($manager->decide($this->token(), ['TENANT_CREATE']));
    }

    public function testEveryOtherAttributeKeepsTheDefaultStrategy(): void
    {
        $manager = $this->createManager();

        self::assertTrue($manager->decide($this->token(), ['SOMETHING_ELSE']));
    }

    /**
     * A decision about several attributes at once is one decision about all of them — an
     * `access_control` rule listing roles — so picking a strategy from one member would be arbitrary.
     */
    public function testADecisionAboutSeveralAttributesKeepsTheDefaultStrategy(): void
    {
        $manager = $this->createManager();

        self::assertTrue($manager->decide($this->token(), ['TENANT_CREATE', 'SOMETHING_ELSE'], null, null, true));
    }

    /**
     * Attributes are not always strings — an expression, an enum — and those cannot key the map.
     */
    public function testANonStringAttributeKeepsTheDefaultStrategy(): void
    {
        $manager = $this->createManager();

        self::assertTrue($manager->decide($this->token(), [new stdClass()]));
    }

    private function createManager(): PerAttributeAccessDecisionManager
    {
        return new PerAttributeAccessDecisionManager(
            [$this->voter(VoterInterface::ACCESS_GRANTED), $this->voter(VoterInterface::ACCESS_DENIED)],
            null,
            [
                'TENANT_CREATE' => new UnanimousStrategy(),
            ],
        );
    }

    private function voter(int $result): VoterInterface
    {
        $voter = self::createStub(VoterInterface::class);
        $voter->method('vote')->willReturn($result);

        return $voter;
    }

    private function token(): TokenInterface
    {
        return self::createStub(TokenInterface::class);
    }
}
