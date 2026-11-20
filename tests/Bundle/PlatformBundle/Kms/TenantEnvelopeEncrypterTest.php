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

use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Kms\Exception\MissingTenantContextException;
use SolidWorx\Platform\PlatformBundle\Kms\TenantEnvelopeEncrypter;
use SolidWorx\Platform\PlatformBundle\Kms\TenantScopeRegistry;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;
use Symfony\Component\Uid\Ulid;
use function class_exists;
use function pack;
use function random_bytes;
use function strlen;

#[CoversClass(TenantEnvelopeEncrypter::class)]
#[CoversClass(MissingTenantContextException::class)]
#[RequiresPhpExtension('sodium')]
final class TenantEnvelopeEncrypterTest extends TestCase
{
    private const string LABEL = 'symfony/key-management/doctrine-dbal/encrypted-type/v1';

    private StoredEnvelopeEncrypter $inner;

    private TenantContext $tenantContext;

    private TenantEnvelopeEncrypter $encrypter;

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

        // The registry's cache stands in for ORM metadata: these two scopes are tenant-aware columns.
        $cache = new ArrayAdapter();
        $cache->save($cache->getItem('platform_kms_tenant_scopes')->set([
            'orm:invoice.notes' => true,
            'orm:invoice.total' => true,
        ]));

        $this->tenantContext = new TenantContext(new EventDispatcher());
        $this->encrypter = new TenantEnvelopeEncrypter(
            $this->inner,
            new TenantScopeRegistry(self::createStub(ManagerRegistry::class), $cache),
            $this->tenantContext,
        );
    }

    public function testNonTenantScopePassesThrough(): void
    {
        $envelope = $this->encrypter->encrypt('orm:user.email', 'secret', self::context('orm:user.email'));

        $this->assertSame('secret', $this->inner->decrypt($envelope, self::context('orm:user.email')));
        $this->assertSame('secret', $this->encrypter->decrypt($envelope, self::context('orm:user.email')));
    }

    public function testNonDoctrineAadPassesThrough(): void
    {
        $envelope = $this->inner->encrypt('orm:invoice.notes', 'secret', 'some other context');

        $this->assertSame('secret', $this->encrypter->decrypt($envelope, 'some other context'));
    }

    public function testTenantScopeUsesTenantDataKey(): void
    {
        $this->tenantContext->setTenant($tenantA = new Ulid());
        $notes = $this->encrypter->encrypt('orm:invoice.notes', 'a notes', self::context('orm:invoice.notes'));
        $total = $this->encrypter->encrypt('orm:invoice.total', 'a total', self::context('orm:invoice.total'));

        $this->assertNotNull($notes->reference);
        $this->assertSame($notes->reference, $total->reference);
        $this->assertSame('a notes', $this->encrypter->decrypt($notes, self::context('orm:invoice.notes')));
        $this->assertSame('a total', $this->encrypter->decrypt($total, self::context('orm:invoice.total')));

        $this->tenantContext->setTenant(new Ulid());
        $other = $this->encrypter->encrypt('orm:invoice.notes', 'b notes', self::context('orm:invoice.notes'));

        $this->assertNotSame($notes->reference, $other->reference);

        // The tenant's data key is the one stored under its own scope, not the column's.
        $this->assertNotSame($notes->reference, $this->inner->encrypt('orm:invoice.notes', 'x')->reference);
        $this->assertSame($notes->reference, $this->inner->encrypt(TenantEnvelopeEncrypter::SCOPE_PREFIX . $tenantA->toBase32(), 'x')->reference);
    }

    public function testEncryptWithoutTenantThrows(): void
    {
        $this->expectException(MissingTenantContextException::class);
        $this->expectExceptionMessageIsOrContains('No tenant is active for the encryption scope "orm:invoice.notes". Encrypted tenant data can only be accessed inside a tenant context.');

        $this->encrypter->encrypt('orm:invoice.notes', 'secret', self::context('orm:invoice.notes'));
    }

    public function testDecryptWithoutTenantThrows(): void
    {
        $this->tenantContext->setTenant(new Ulid());
        $envelope = $this->encrypter->encrypt('orm:invoice.notes', 'secret', self::context('orm:invoice.notes'));
        $this->tenantContext->clear();

        $this->expectException(MissingTenantContextException::class);

        $this->encrypter->decrypt($envelope, self::context('orm:invoice.notes'));
    }

    public function testDecryptUnderOtherTenantFails(): void
    {
        $this->tenantContext->setTenant(new Ulid());
        $envelope = $this->encrypter->encrypt('orm:invoice.notes', 'secret', self::context('orm:invoice.notes'));
        $this->tenantContext->setTenant(new Ulid());

        $this->expectException(DecryptionFailedException::class);

        $this->encrypter->decrypt($envelope, self::context('orm:invoice.notes'));
    }

    public function testInnerDecryptWithoutTenantSuffixFails(): void
    {
        $this->tenantContext->setTenant(new Ulid());
        $envelope = $this->encrypter->encrypt('orm:invoice.notes', 'secret', self::context('orm:invoice.notes'));

        $this->expectException(DecryptionFailedException::class);

        $this->inner->decrypt($envelope, self::context('orm:invoice.notes'));
    }

    public function testTenantSuffixIsBoundToTheAad(): void
    {
        $this->tenantContext->setTenant($tenant = new Ulid());
        $envelope = $this->encrypter->encrypt('orm:invoice.notes', 'secret', self::context('orm:invoice.notes'));

        $this->assertSame('secret', $this->inner->decrypt(
            $envelope,
            self::context('orm:invoice.notes') . 'solidworx/platform/kms/tenant/v1' . pack('n', 16) . $tenant->toBinary(),
        ));
    }

    public function testSharedScopeWithTenantPrefixIsRefused(): void
    {
        $this->expectException(LogicException::class);

        $this->encrypter->encrypt(TenantEnvelopeEncrypter::SCOPE_PREFIX . new Ulid()->toBase32(), 'secret');
    }

    #[DataProvider('malformedAad')]
    public function testMalformedEncryptedTypeAadThrows(string $aad): void
    {
        $envelope = $this->inner->encrypt('orm:user.email', 'secret', $aad);

        $this->expectException(LogicException::class);

        $this->encrypter->decrypt($envelope, $aad);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedAad(): iterable
    {
        yield 'no key length' => [self::LABEL . "\x00"];
        yield 'truncated key' => [self::LABEL . pack('n', 40) . 'orm:invoice.notes'];
        yield 'no type length' => [self::LABEL . pack('n', 17) . 'orm:invoice.notes'];
        yield 'truncated type' => [self::LABEL . pack('n', 17) . 'orm:invoice.notes' . pack('n', 10) . 'str'];
        yield 'trailing bytes' => [self::context('orm:invoice.notes') . 'x'];
    }

    /**
     * The AAD Symfony's EncryptedType passes for a column encrypted under $key.
     */
    private static function context(string $key, string $type = 'string'): string
    {
        return self::LABEL . pack('n', strlen($key)) . $key . pack('n', strlen($type)) . $type;
    }
}
