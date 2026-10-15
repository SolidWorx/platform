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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Security\TwoFactor;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use SolidWorx\Platform\PlatformBundle\Security\TwoFactor\Traits\UserTwoFactor;

#[CoversTrait(UserTwoFactor::class)]
final class UserTwoFactorTest extends TestCase
{
    /**
     * Authenticator apps that ignore the QR code's period always use 30 seconds, so any other
     * period makes their codes fail most of the time.
     */
    public function testTotpUsesTheStandardThirtySecondPeriod(): void
    {
        $user = new class() {
            use UserTwoFactor;

            private string $email = 'user@example.com';
        };
        $user->setTotpSecret('JBSWY3DPEHPK3PXP');

        $configuration = $user->getTotpAuthenticationConfiguration();

        self::assertNotNull($configuration);
        self::assertSame(30, $configuration->getPeriod());
        self::assertSame(6, $configuration->getDigits());
        self::assertSame(TotpConfiguration::ALGORITHM_SHA1, $configuration->getAlgorithm());
    }
}
