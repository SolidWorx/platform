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

use Override;
use SensitiveParameter;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface as PlatformUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuses authentication to users whose email address is not verified.
 *
 * Not wired to any firewall by the platform: interactive sign-in should let an unverified user in
 * (the banner nudges them, the gate limits what they can do), whereas token-based channels such as
 * an API are usually better closed until they verify. Use it as a firewall's `user_checker`, or
 * tag it `security.user_checker.<firewall>` and point the firewall at
 * `security.user_checker.chain.<firewall>` to combine it with other checkers.
 */
final readonly class VerifiedUserChecker implements UserCheckerInterface
{
    public function __construct(
        private EmailVerificationGateInterface $gate,
    ) {
    }

    #[Override]
    public function checkPreAuth(UserInterface $user): void
    {
    }

    #[Override]
    public function checkPostAuth(UserInterface $user, #[SensitiveParameter] ?TokenInterface $token = null): void
    {
        if ($user instanceof PlatformUserInterface && $this->gate->isGated($user)) {
            throw new CustomUserMessageAccountStatusException('Please verify your email address before continuing.');
        }
    }
}
