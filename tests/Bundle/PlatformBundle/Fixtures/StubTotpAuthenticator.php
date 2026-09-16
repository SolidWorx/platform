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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures;

use Override;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

/**
 * A TOTP authenticator that does no cryptography.
 *
 * Rendering tests need the service to exist — the `TwoFactorCode` constraint resolves its validator
 * from the container the moment a form is validated — but not to agree with a real authenticator
 * app. Every code is wrong, which is the case those tests are about.
 */
final class StubTotpAuthenticator implements TotpAuthenticatorInterface
{
    public const string SECRET = 'JBSWY3DPEHPK3PXP';

    #[Override]
    public function checkCode(TwoFactorInterface $user, string $code): bool
    {
        return false;
    }

    #[Override]
    public function getQRContent(TwoFactorInterface $user): string
    {
        return 'otpauth://totp/Acme:ada@example.com?secret=' . self::SECRET;
    }

    #[Override]
    public function generateSecret(): string
    {
        return self::SECRET;
    }
}
