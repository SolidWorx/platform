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

namespace SolidWorx\Platform\PlatformBundle\Security\EmailVerification;

use SolidWorx\Platform\PlatformBundle\Model\UserInterface;

/**
 * Decides whether a user is held back because their email address is not verified.
 *
 * Inject it wherever an action should wait for verification (sending mail on the user's behalf,
 * enabling automation, opening an API channel), or use the `is_email_verification_gated()` Twig
 * function in templates.
 */
interface EmailVerificationGateInterface
{
    /**
     * True when email verification is enabled and the user — the signed-in user when none is
     * given — has not verified their email address. Always false for anonymous visitors.
     */
    public function isGated(?UserInterface $user = null): bool;

    /**
     * A message explaining why an action is blocked, e.g. reason('sending this invoice') gives
     * "Please verify your email address before sending this invoice."
     */
    public function reason(string $action): string;
}
