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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Security;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Trusted\TrustedDeviceManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Trusted\TrustedDeviceTokenStorage;
use SolidWorx\Platform\PlatformBundle\Contracts\Doctrine\Repository\UserRepository;
use SolidWorx\Platform\PlatformBundle\Security\TwoFactor\BackupCodeGeneratorInterface;
use SolidWorx\Platform\PlatformBundle\Twig\Components\Security\TwoFactor;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * The name the browser saves the backup codes as.
 *
 * Downloads land in one folder, so a file called `backup-codes.txt` is indistinguishable from
 * every other application's — and from the same user's second account.
 */
#[CoversClass(TwoFactor::class)]
final class TwoFactorFilenameTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function applicationNames(): iterable
    {
        yield 'one word' => ['SolidInvoice', 'solidinvoice-backup-codes.txt'];
        yield 'two words' => ['Acme Platform', 'acme-platform-backup-codes.txt'];
        yield 'punctuation' => ['Acme, Inc.', 'acme-inc-backup-codes.txt'];
        yield 'accents' => ['Café Ltd', 'cafe-ltd-backup-codes.txt'];
        // A filename is not the place to discover that the application has no name.
        yield 'no name configured' => ['', 'backup-codes.txt'];
    }

    #[DataProvider('applicationNames')]
    public function testTheFilenameLeadsWithTheApplicationName(string $appName, string $expected): void
    {
        self::assertSame($expected, self::component($appName)->backupCodesFilename());
    }

    private static function component(string $appName): TwoFactor
    {
        return new TwoFactor(
            self::createStub(UserRepository::class),
            self::createStub(TotpAuthenticatorInterface::class),
            self::createStub(TrustedDeviceTokenStorage::class),
            self::createStub(TrustedDeviceManagerInterface::class),
            self::createStub(BackupCodeGeneratorInterface::class),
            new AsciiSlugger(),
            $appName,
        );
    }
}
