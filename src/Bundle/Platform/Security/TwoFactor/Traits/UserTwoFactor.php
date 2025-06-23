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

namespace SolidWorx\Platform\PlatformBundle\Security\TwoFactor\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use function array_search;
use function in_array;

trait UserTwoFactor
{
    #[ORM\Column(name: 'totp_secret', type: Types::STRING, length: 45, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\Column(name: 'auth_code', type: Types::STRING, length: 45, nullable: true)]
    private ?string $authCode = null;

    #[ORM\Column(name: 'email_auth_enabled', type: Types::BOOLEAN, nullable: true)]
    private ?bool $emailAuthEnabled = false;

    #[ORM\Column(name: 'trusted_version', type: Types::INTEGER, options: [
        'default' => 0,
    ])]
    private int $trustedVersion = 0;

    #[ORM\Column(name: 'backup_codes', type: 'json', nullable: true)]
    private ?array $backupCodes = [];

    public function isTotpAuthenticationEnabled(): bool
    {
        return (bool) $this->totpSecret;
    }

    public function setTotpSecret(string $totpSecret): self
    {
        $this->totpSecret = $totpSecret;

        return $this;
    }

    public function getTotpAuthenticationUsername(): string
    {
        // @TODO: email property should not be hard-coded
        return $this->email;
    }

    public function getTotpAuthenticationConfiguration(): TotpConfigurationInterface | null
    {
        $period = 20;
        $digits = 6;

        return $this->totpSecret !== null ? new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, $period, $digits) : null;
    }

    public function isEmailAuthEnabled(): bool
    {
        return $this->emailAuthEnabled === true;
    }

    public function getEmailAuthRecipient(): string
    {
        // @TODO: email property should not be hard-coded
        return $this->email;
    }

    public function getEmailAuthCode(): string | null
    {
        if ($this->authCode === null) {
            throw new LogicException('The email authentication code was not set');
        }

        return $this->authCode;
    }

    public function setEmailAuthCode(string $authCode): void
    {
        $this->authCode = $authCode;
    }

    public function is2FaEnabled(): bool
    {
        if ($this->isTotpAuthenticationEnabled()) {
            return true;
        }
        return (bool) $this->isEmailAuthEnabled();
    }

    public function getTrustedTokenVersion(): int
    {
        return $this->trustedVersion;
    }

    public function isBackupCode(string $code): bool
    {
        return in_array($code, (array) $this->backupCodes, true);
    }

    public function invalidateBackupCode(string $code): void
    {
        $key = array_search($code, (array) $this->backupCodes, true);
        if ($key !== false) {
            unset($this->backupCodes[$key]);
        }
    }

    /**
     * @param list<string> $backUpCodes
     */
    public function setBackUpCodes(array $backUpCodes): self
    {
        $this->backupCodes = $backUpCodes;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getBackUpCodes(): array
    {
        return $this->backupCodes ?? [];
    }

    public function enableEmailAuth(bool $enabled): self
    {
        $this->emailAuthEnabled = $enabled;

        return $this;
    }

    public function getPreferredTwoFactorProvider(): string|null
    {
        return 'totp';
    }
}
