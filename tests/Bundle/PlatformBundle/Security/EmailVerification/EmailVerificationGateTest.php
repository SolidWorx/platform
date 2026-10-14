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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Security\EmailVerification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Security\EmailVerification\EmailVerificationGate;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\ProfileUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Translation\IdentityTranslator;

#[CoversClass(EmailVerificationGate::class)]
final class EmailVerificationGateTest extends TestCase
{
    public function testGatesTheSignedInUserUntilTheyVerify(): void
    {
        $user = new ProfileUser();

        self::assertTrue($this->gate($user)->isGated());

        $user->setVerified(true);

        self::assertFalse($this->gate($user)->isGated());
    }

    public function testChecksAGivenUserInsteadOfTheSignedInOne(): void
    {
        self::assertTrue($this->gate(null)->isGated(new ProfileUser()));
    }

    public function testNeverGatesWhenVerificationIsDisabled(): void
    {
        self::assertFalse($this->gate(new ProfileUser(), enabled: false)->isGated());
    }

    public function testNeverGatesAnonymousVisitorsOrNonPlatformUsers(): void
    {
        self::assertFalse($this->gate(null)->isGated());
        self::assertFalse($this->gate(new InMemoryUser('api', null))->isGated());
    }

    public function testExplainsWhatTheUserHasToDoFirst(): void
    {
        self::assertSame(
            'Please verify your email address before sending this invoice.',
            $this->gate(null)->reason('sending this invoice'),
        );
    }

    private function gate(?UserInterface $user, bool $enabled = true): EmailVerificationGate
    {
        $security = self::createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new EmailVerificationGate($security, new IdentityTranslator(), $enabled);
    }
}
