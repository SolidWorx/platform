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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Enum\PasswordStrengthLevel;
use Symfony\Component\Validator\Constraints\PasswordStrength;

#[CoversClass(PasswordStrengthLevel::class)]
final class PasswordStrengthLevelTest extends TestCase
{
    /**
     * @return iterable<string, array{PasswordStrengthLevel, PasswordStrength::STRENGTH_*|null}>
     */
    public static function scores(): iterable
    {
        yield 'none' => [PasswordStrengthLevel::None, null];
        yield 'weak' => [PasswordStrengthLevel::Weak, PasswordStrength::STRENGTH_WEAK];
        yield 'medium' => [PasswordStrengthLevel::Medium, PasswordStrength::STRENGTH_MEDIUM];
        yield 'strong' => [PasswordStrengthLevel::Strong, PasswordStrength::STRENGTH_STRONG];
        yield 'very strong' => [PasswordStrengthLevel::VeryStrong, PasswordStrength::STRENGTH_VERY_STRONG];
    }

    /**
     * @param PasswordStrength::STRENGTH_*|null $expected
     */
    #[DataProvider('scores')]
    public function testItMapsOntoTheConstraintScore(PasswordStrengthLevel $level, ?int $expected): void
    {
        self::assertSame($expected, $level->minScore());
    }

    /**
     * Every score it returns has to be one the constraint accepts, or building the constraint
     * throws at runtime instead of failing the configuration.
     *
     * @param PasswordStrength::STRENGTH_*|null $minScore
     */
    #[DataProvider('scores')]
    public function testEveryScoreIsAcceptedByTheConstraint(PasswordStrengthLevel $level, ?int $minScore): void
    {
        if ($minScore === null) {
            self::assertSame(PasswordStrengthLevel::None, $level);

            return;
        }

        self::assertSame($minScore, new PasswordStrength(minScore: $minScore)->minScore);
    }

    public function testOnlyTheDisabledLevelHasNothingToTellTheUser(): void
    {
        self::assertNull(PasswordStrengthLevel::None->requirement());

        foreach (PasswordStrengthLevel::cases() as $level) {
            if ($level === PasswordStrengthLevel::None) {
                continue;
            }

            self::assertNotNull($level->requirement(), $level->value);
        }
    }

    public function testValuesCoversEveryCase(): void
    {
        self::assertSame(
            ['none', 'weak', 'medium', 'strong', 'very_strong'],
            PasswordStrengthLevel::values(),
        );
    }
}
