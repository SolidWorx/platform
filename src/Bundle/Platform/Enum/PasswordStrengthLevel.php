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

namespace SolidWorx\Platform\PlatformBundle\Enum;

use Symfony\Component\Validator\Constraints\PasswordStrength;
use function array_column;

/**
 * The strength a new password has to reach, as configured under
 * `platform.profile.password.strength`.
 *
 * Every level except {@see self::None} maps onto a `minScore` of Symfony's
 * {@see PasswordStrength} constraint, which estimates entropy rather than counting character
 * classes — a long passphrase scores well without needing a symbol in it.
 */
enum PasswordStrengthLevel: string
{
    /**
     * Do not check the strength at all; only the remaining rules (length, breach check) apply.
     */
    case None = 'none';

    case Weak = 'weak';

    case Medium = 'medium';

    case Strong = 'strong';

    case VeryStrong = 'very_strong';

    /**
     * The configuration values accepted for `platform.profile.password.strength`.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The `minScore` to build {@see PasswordStrength} with, or `null` when strength is not enforced.
     *
     * @return PasswordStrength::STRENGTH_*|null
     */
    public function minScore(): ?int
    {
        return match ($this) {
            self::None => null,
            self::Weak => PasswordStrength::STRENGTH_WEAK,
            self::Medium => PasswordStrength::STRENGTH_MEDIUM,
            self::Strong => PasswordStrength::STRENGTH_STRONG,
            self::VeryStrong => PasswordStrength::STRENGTH_VERY_STRONG,
        };
    }

    /**
     * A human description of the level, listed to the user next to the new-password field.
     *
     * `null` for {@see self::None}, which has nothing to tell the user about.
     */
    public function requirement(): ?string
    {
        return match ($this) {
            self::None => null,
            self::Weak => 'Not one of the most commonly used passwords',
            self::Medium => 'Not a common word or an obvious pattern',
            self::Strong => 'A long, unpredictable mix of words, numbers or symbols',
            self::VeryStrong => 'A long passphrase, or an unpredictable mix of letters, numbers and symbols',
        };
    }
}
