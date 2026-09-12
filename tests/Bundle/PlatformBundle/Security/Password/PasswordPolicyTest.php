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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Security\Password;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Enum\PasswordStrengthLevel;
use SolidWorx\Platform\PlatformBundle\Security\Password\PasswordPolicy;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use function array_map;

#[CoversClass(PasswordPolicy::class)]
#[UsesClass(PasswordStrengthLevel::class)]
final class PasswordPolicyTest extends TestCase
{
    public function testTheDefaultPolicyChecksLengthStrengthAndBreaches(): void
    {
        $policy = new PasswordPolicy(12, PasswordStrengthLevel::Medium, true);

        self::assertSame(
            [NotBlank::class, Length::class, PasswordStrength::class, NotCompromisedPassword::class],
            self::classesOf($policy->constraints()),
        );
    }

    public function testTheConfiguredMinimumLengthIsTheOneEnforced(): void
    {
        $constraints = new PasswordPolicy(20, PasswordStrengthLevel::None, false)->constraints();

        $length = $constraints[1];

        self::assertInstanceOf(Length::class, $length);
        self::assertSame(20, $length->min);
    }

    public function testTheConfiguredStrengthIsTheOneEnforced(): void
    {
        $constraints = new PasswordPolicy(12, PasswordStrengthLevel::VeryStrong, false)->constraints();

        $strength = $constraints[2];

        self::assertInstanceOf(PasswordStrength::class, $strength);
        self::assertSame(PasswordStrength::STRENGTH_VERY_STRONG, $strength->minScore);
    }

    public function testStrengthCanBeTurnedOff(): void
    {
        $policy = new PasswordPolicy(12, PasswordStrengthLevel::None, true);

        self::assertSame(
            [NotBlank::class, Length::class, NotCompromisedPassword::class],
            self::classesOf($policy->constraints()),
        );
    }

    public function testTheBreachCheckCanBeTurnedOff(): void
    {
        $policy = new PasswordPolicy(12, PasswordStrengthLevel::Medium, false);

        self::assertSame(
            [NotBlank::class, Length::class, PasswordStrength::class],
            self::classesOf($policy->constraints()),
        );
    }

    /**
     * An outage at the breach API must not stand between somebody and a password rotation.
     */
    public function testTheBreachCheckIsSkippedWhenTheApiCannotBeReached(): void
    {
        $constraints = new PasswordPolicy(12, PasswordStrengthLevel::None, true)->constraints();

        $breachCheck = $constraints[2];

        self::assertInstanceOf(NotCompromisedPassword::class, $breachCheck);
        self::assertTrue($breachCheck->skipOnError);
    }

    /**
     * The list shown to the user is what makes the rules discoverable, so it has to describe
     * every rule that is actually switched on — and nothing that is not.
     */
    public function testTheRequirementsDescribeExactlyTheRulesInForce(): void
    {
        $requirements = new PasswordPolicy(16, PasswordStrengthLevel::Strong, true)->requirements();

        self::assertCount(3, $requirements);
        self::assertStringContainsString('16', $requirements[0]);
        self::assertSame(PasswordStrengthLevel::Strong->requirement(), $requirements[1]);
        self::assertStringContainsString('data breach', $requirements[2]);
    }

    public function testTheRequirementsDropTheRulesThatAreTurnedOff(): void
    {
        $requirements = new PasswordPolicy(8, PasswordStrengthLevel::None, false)->requirements();

        self::assertCount(1, $requirements);
        self::assertStringContainsString('8', $requirements[0]);
    }

    /**
     * @param list<Constraint> $constraints
     *
     * @return list<string>
     */
    private static function classesOf(array $constraints): array
    {
        return array_map(static fn (Constraint $constraint): string => $constraint::class, $constraints);
    }
}
