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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Security\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Entity\User;
use SolidWorx\Platform\PlatformBundle\Security\EventListener\DisabledUserLoginListener;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

#[CoversClass(DisabledUserLoginListener::class)]
final class DisabledUserLoginListenerTest extends TestCase
{
    public function testRejectsADisabledUser(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessageIsOrContains('Your account has been disabled. Please contact support if you believe this is a mistake.');

        new DisabledUserLoginListener()($this->createEvent(new User()->setEnabled(false)));
    }

    public function testAllowsAnEnabledUser(): void
    {
        new DisabledUserLoginListener()($this->createEvent(new User()->setEnabled(true)));

        $this->expectNotToPerformAssertions();
    }

    private function createEvent(UserInterface $user): CheckPassportEvent
    {
        return new CheckPassportEvent(
            self::createStub(AuthenticatorInterface::class),
            new Passport(
                new UserBadge('user@example.com', static fn (): UserInterface => $user),
                new CustomCredentials(static fn (): bool => true, 'secret'),
            ),
        );
    }
}
