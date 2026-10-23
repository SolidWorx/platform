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

namespace SolidWorx\Platform\PlatformBundle\Security\EventListener;

use SolidWorx\Platform\PlatformBundle\Model\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Refuses authentication to a disabled user, on every firewall and every authenticator (form login,
 * remember-me, API tokens).
 *
 * Runs after the credentials are checked (priority -100, the password check is at 0), so only
 * someone who knows the password learns the account is disabled; anyone else still gets the
 * generic "Invalid credentials." and cannot use the form to probe for accounts.
 *
 * An already signed-in user who is disabled is logged out on their next request by
 * {@see \SolidWorx\Platform\PlatformBundle\Repository\UserRepository::refreshUser()}.
 */
#[AsEventListener(event: CheckPassportEvent::class, priority: -100)]
final readonly class DisabledUserLoginListener
{
    public function __invoke(CheckPassportEvent $event): void
    {
        $user = $event->getPassport()->getUser();

        if ($user instanceof User && ! $user->isEnabled()) {
            throw new CustomUserMessageAccountStatusException('Your account has been disabled. Please contact support if you believe this is a mistake.');
        }
    }
}
