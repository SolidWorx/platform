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

namespace SolidWorx\Platform\PlatformBundle\Validator\Constraint;

use RuntimeException;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use SolidWorx\Platform\PlatformBundle\Contracts\Security\TwoFactor\UserTwoFactorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class TwoFactorCodeValidator extends ConstraintValidator
{
    public function __construct(
        private readonly TotpAuthenticatorInterface $totpAuthenticator,
        private readonly Security $security,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof TwoFactorCode) {
            throw new UnexpectedTypeException($constraint, TwoFactorCode::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (! \is_scalar($value) && ! $value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;

        $user = $this->security->getUser();

        if (! $user instanceof UserTwoFactorInterface) {
            throw new RuntimeException(sprintf('User must implement the %s interface to use the %s validator.', UserTwoFactorInterface::class, TwoFactorCode::class));
        }

        // Prevent updating the original user object with the secret code
        $user = clone $user;

        $user->setTotpSecret($constraint->secret);

        if (! $this->totpAuthenticator->checkCode($user, $value)) {
            $this->context->buildViolation($constraint->message)
                ->setCode(TwoFactorCode::INVALID_CODE_ERROR)
                ->addViolation();
        }
    }
}
