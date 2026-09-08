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

use Symfony\Component\Validator\Constraint;

/**
 * The rules a user-chosen password has to satisfy.
 *
 * One service owns both halves of a password rule — the constraint that enforces it and the
 * sentence that explains it — so the list shown on the change-password page can never drift
 * away from what is actually validated.
 *
 * The default implementation is driven by `platform.profile.password`. Replace it wholesale to
 * enforce something the configuration does not cover:
 *
 *     #[AsDecorator(PasswordPolicyInterface::class)]
 *     final readonly class MyPasswordPolicy implements PasswordPolicyInterface { … }
 */
interface PasswordPolicyInterface
{
    /**
     * The constraints to validate a submitted plain-text password with.
     *
     * @return list<Constraint>
     */
    public function constraints(): array;

    /**
     * The same rules as short sentences, for display next to the password field.
     *
     * @return list<string>
     */
    public function requirements(): array;
}
