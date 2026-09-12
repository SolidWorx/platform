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

namespace SolidWorx\Platform\PlatformBundle\Security\Password;

use Override;
use SolidWorx\Platform\PlatformBundle\Enum\PasswordStrengthLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use function sprintf;

/**
 * The password rules described by `platform.profile.password`.
 *
 * The breach check is deliberately built with `skipOnError: true`: it calls the
 * haveibeenpwned range API, and an outage there must not stop somebody from rotating a password
 * they believe to be compromised.
 */
final readonly class PasswordPolicy implements PasswordPolicyInterface
{
    /**
     * Matches the `max` Symfony's own security recommendations use, and keeps a submitted
     * "password" from being large enough to be worth hashing.
     */
    private const int MAX_LENGTH = 4096;

    /**
     * @param int<1, max> $minLength The `min_length` node refuses anything lower than 1
     */
    public function __construct(
        #[Autowire(param: 'solidworx_platform.profile.password.min_length')]
        private int $minLength,
        #[Autowire(param: 'solidworx_platform.profile.password.strength')]
        private PasswordStrengthLevel $strength,
        #[Autowire(param: 'solidworx_platform.profile.password.check_compromised')]
        private bool $checkCompromised,
    ) {
    }

    /**
     * @return list<Constraint>
     */
    #[Override]
    public function constraints(): array
    {
        $constraints = [
            new NotBlank(),
            new Length(
                min: $this->minLength,
                max: self::MAX_LENGTH,
                minMessage: 'Your password must be at least {{ limit }} characters long.',
                maxMessage: 'Your password cannot be longer than {{ limit }} characters.',
            ),
        ];

        $minScore = $this->strength->minScore();

        if ($minScore !== null) {
            $constraints[] = new PasswordStrength(minScore: $minScore);
        }

        if ($this->checkCompromised) {
            $constraints[] = new NotCompromisedPassword(skipOnError: true);
        }

        return $constraints;
    }

    #[Override]
    public function requirements(): array
    {
        $requirements = [sprintf('At least %d characters long', $this->minLength)];

        $strength = $this->strength->requirement();

        if ($strength !== null) {
            $requirements[] = $strength;
        }

        if ($this->checkCompromised) {
            $requirements[] = 'Not found in any known data breach';
        }

        return $requirements;
    }
}
