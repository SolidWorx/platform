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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Kms;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SolidWorx\Platform\PlatformBundle\Kms\LocalKeyGuardEnvelopeEncrypter;
use SolidWorx\Platform\PlatformBundle\Kms\LocalKeyManager;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;
use function class_exists;
use function random_bytes;

#[CoversClass(LocalKeyGuardEnvelopeEncrypter::class)]
#[UsesClass(LocalKeyManager::class)]
#[RequiresPhpExtension('sodium')]
final class LocalKeyGuardEnvelopeEncrypterTest extends TestCase
{
    private StoredEnvelopeEncrypter $inner;

    #[Override]
    protected function setUp(): void
    {
        if (! class_exists(Encrypted::class)) {
            self::markTestSkipped('symfony/doctrine-orm-key-management is not installed.');
        }

        $this->inner = new StoredEnvelopeEncrypter(new InMemoryDataKeyStore([
            'default' => new SodiumKms(new InMemoryKeyLoader([
                'app' => random_bytes(32),
            ])),
        ]));
    }

    public function testGuardThrowsInProdWithLocalKeyOnFirstUse(): void
    {
        $guard = new LocalKeyGuardEnvelopeEncrypter($this->inner, $this->manager('sodium+dir:///etc/keys'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('local master key in production');

        $guard->encrypt('app', 'secret');
    }

    public function testGuardThrowsOnDecryptToo(): void
    {
        $envelope = $this->inner->encrypt('app', 'secret');
        $guard = new LocalKeyGuardEnvelopeEncrypter($this->inner, $this->manager('sodium+dir:///etc/keys'));

        $this->expectException(RuntimeException::class);

        $guard->decrypt($envelope);
    }

    public function testGuardKeepsThrowingAfterFailure(): void
    {
        $guard = new LocalKeyGuardEnvelopeEncrypter($this->inner, $this->manager('sodium+dir:///etc/keys'));
        $envelope = $this->inner->encrypt('app', 'secret');

        $failures = 0;

        foreach ([
            static fn (): mixed => $guard->encrypt('app', 'secret'),
            static fn (): mixed => $guard->encrypt('app', 'secret'),
            static fn (): mixed => $guard->decrypt($envelope),
        ] as $call) {
            try {
                $call();
            } catch (RuntimeException) {
                ++$failures;
            }
        }

        $this->assertSame(3, $failures);
    }

    public function testGuardChecksOnlyOnce(): void
    {
        // In prod without a store secret, every check() logs one warning: the count of warnings is
        // the count of checks.
        $logger = new BufferingLogger();
        $guard = new LocalKeyGuardEnvelopeEncrypter($this->inner, $this->manager('aws-kms://default?region=eu-west-1', $logger));

        $envelope = $guard->encrypt('app', 'secret');
        $guard->encrypt('app', 'secret');
        $this->assertSame('secret', $guard->decrypt($envelope));

        $this->assertCount(1, $logger->cleanLogs());
    }

    public function testGuardDelegatesWhenAllowed(): void
    {
        $guard = new LocalKeyGuardEnvelopeEncrypter($this->inner, $this->manager('aws-kms://default?region=eu-west-1'));

        $envelope = $guard->encrypt('app', 'secret', 'aad');

        $this->assertSame('secret', $this->inner->decrypt($envelope, 'aad'));
        $this->assertSame('secret', $guard->decrypt($envelope, 'aad'));
    }

    private function manager(string $dsn, ?BufferingLogger $logger = null): LocalKeyManager
    {
        return new LocalKeyManager($dsn, 'platform', 'prod', false, $logger, '');
    }
}
