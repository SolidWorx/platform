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

namespace SolidWorx\Platform\PlatformBundle\Contracts\Security\TwoFactor;

use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface as EmailTwoFactorInterface;
use Scheb\TwoFactorBundle\Model\PreferredProviderInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface as TotpTwoFactorInterface;
use Scheb\TwoFactorBundle\Model\TrustedDeviceInterface;

interface UserTwoFactorInterface extends TotpTwoFactorInterface, EmailTwoFactorInterface, TrustedDeviceInterface, BackupCodeInterface, PreferredProviderInterface
{
    public function enableEmailAuth(bool $enabled): self;

    /**
     * @param list<string> $backUpCodes
     */
    public function setBackUpCodes(array $backUpCodes): self;

    public function is2FaEnabled(): bool;

    /**
     * @return list<string>
     */
    public function getBackupCodes(): array;

    public function setTotpSecret(string $totpSecret): self;
}
