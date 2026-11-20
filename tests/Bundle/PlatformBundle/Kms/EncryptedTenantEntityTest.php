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

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use SolidWorx\Platform\PlatformBundle\Kms\Exception\MissingTenantContextException;
use SolidWorx\Platform\PlatformBundle\Kms\TenantEnvelopeEncrypter;
use SolidWorx\Platform\PlatformBundle\Kms\TenantScopeRegistry;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Kms\EndToEnd\EncryptedTenantNullable;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Kms\EndToEnd\EncryptedTenantRecord;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Kms\Entity\EncryptedTenantNote;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Kms\Entity\EncryptedUserProfile;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Kms\KmsOrmTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\Uid\Ulid;
use Throwable;
use function sprintf;

#[CoversNothing]
#[RequiresPhpExtension('sodium')]
#[RequiresPhpExtension('openssl')]
final class EncryptedTenantEntityTest extends KmsOrmTestCase
{
    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantContext = new TenantContext(new EventDispatcher());
    }

    public function testTenantEntityRoundTrip(): void
    {
        $em = $this->entityManagerFor('Entity');
        $this->tenantContext->setTenant($tenant = $this->ulid());

        $id = $this->persistNote($em, $tenant, 'top secret');
        $em->clear();

        $note = $em->find(EncryptedTenantNote::class, $id);
        self::assertInstanceOf(EncryptedTenantNote::class, $note);
        self::assertSame('top secret', $note->body);

        $raw = $this->rawColumn($em, EncryptedTenantNote::class, 'body', $id);
        self::assertNotSame('top secret', $raw);
        self::assertStringNotContainsString('top secret', $raw);
    }

    public function testNonTenantEntityRoundTripWithoutTenant(): void
    {
        $em = $this->entityManagerFor('Entity');

        $em->persist($profile = new EncryptedUserProfile('+27 82 000 0000'));
        $em->flush();
        $id = $profile->id;
        self::assertNotNull($id);
        $em->clear();

        $loaded = $em->find(EncryptedUserProfile::class, $id);
        self::assertInstanceOf(EncryptedUserProfile::class, $loaded);
        self::assertSame('+27 82 000 0000', $loaded->phone);
        self::assertStringNotContainsString('+27 82', $this->rawColumn($em, EncryptedUserProfile::class, 'phone', $id));
    }

    public function testLoadWithoutTenantThrows(): void
    {
        $em = $this->entityManagerFor('Entity');
        $this->tenantContext->setTenant($tenant = $this->ulid());
        $id = $this->persistNote($em, $tenant, 'top secret');
        $em->clear();
        $this->tenantContext->clear();

        try {
            $em->find(EncryptedTenantNote::class, $id);
            self::fail('Loading encrypted tenant data without a tenant must fail.');
        } catch (Throwable $e) {
            self::assertTrue(self::chainContains($e, MissingTenantContextException::class), 'Expected MissingTenantContextException in the exception chain.');
        }
    }

    public function testCopiedCiphertextFailsForOtherTenant(): void
    {
        $em = $this->entityManagerFor('Entity');
        $this->tenantContext->setTenant($tenantA = $this->ulid());
        $idA = $this->persistNote($em, $tenantA, 'a secret');
        $this->tenantContext->setTenant($tenantB = $this->ulid());
        $idB = $this->persistNote($em, $tenantB, 'b secret');
        $em->clear();

        $table = $em->getClassMetadata(EncryptedTenantNote::class)->getTableName();
        $em->getConnection()->executeStatement(
            sprintf('UPDATE %1$s SET body = (SELECT body FROM %1$s WHERE id = ?) WHERE id = ?', $table),
            [$idA, $idB],
        );

        try {
            $em->find(EncryptedTenantNote::class, $idB);
            self::fail('Ciphertext copied from another tenant must not decrypt.');
        } catch (Throwable $e) {
            self::assertTrue(self::chainContains($e, DecryptionFailedException::class), 'Expected DecryptionFailedException in the exception chain.');
        }

        // The original still decrypts under its own tenant.
        $this->tenantContext->setTenant($tenantA);
        $note = $em->find(EncryptedTenantNote::class, $idA);
        self::assertInstanceOf(EncryptedTenantNote::class, $note);
        self::assertSame('a secret', $note->body);
    }

    public function testNullValueNeedsNoTenant(): void
    {
        $em = $this->entityManagerFor('EndToEnd');

        $em->persist($nullable = new EncryptedTenantNullable());
        $em->flush();
        $id = $nullable->id;
        $em->clear();

        $loaded = $em->find(EncryptedTenantNullable::class, $id);
        self::assertInstanceOf(EncryptedTenantNullable::class, $loaded);
        self::assertNull($loaded->memo);
    }

    public function testDecoratorSeesEveryEncryptedColumn(): void
    {
        $em = $this->entityManagerFor('EndToEnd');
        $this->tenantContext->setTenant($tenantA = $this->ulid());
        $em->persist($a = new EncryptedTenantRecord('implicit a', 'explicit a'));
        $a->setTenantId($tenantA);
        $em->flush();
        $em->clear();

        $loaded = $em->find(EncryptedTenantRecord::class, $a->id);
        self::assertInstanceOf(EncryptedTenantRecord::class, $loaded);
        self::assertSame('implicit a', $loaded->implicit);
        self::assertSame('explicit a', $loaded->explicit);

        // Each column fails on its own under another tenant, so neither path escapes the decorator.
        foreach (['implicit', 'explicit'] as $column) {
            $this->tenantContext->setTenant($tenantB = $this->ulid());
            $em->persist($b = new EncryptedTenantRecord('implicit b', 'explicit b'));
            $b->setTenantId($tenantB);
            $em->flush();
            $em->clear();

            $em->getConnection()->executeStatement(
                sprintf('UPDATE encrypted_tenant_record SET %1$s = (SELECT %1$s FROM encrypted_tenant_record WHERE id = ?) WHERE id = ?', $column),
                [$a->id, $b->id],
            );

            try {
                $em->find(EncryptedTenantRecord::class, $b->id);
                self::fail(sprintf('The %s column must not decrypt under another tenant.', $column));
            } catch (Throwable $e) {
                self::assertTrue(self::chainContains($e, DecryptionFailedException::class), $column);
            }
        }
    }

    private function entityManagerFor(string $dir): EntityManagerInterface
    {
        return $this->createKmsEntityManager(
            __DIR__ . '/../Fixtures/Kms/' . $dir,
            decorate: fn (StoredEnvelopeEncrypter $inner): TenantEnvelopeEncrypter => new TenantEnvelopeEncrypter(
                $inner,
                new TenantScopeRegistry($this->managerRegistry()),
                $this->tenantContext,
            ),
        );
    }

    private function persistNote(EntityManagerInterface $em, Ulid $tenant, string $body): int
    {
        $em->persist($note = new EncryptedTenantNote($body));
        $note->setTenantId($tenant);
        $em->flush();

        return (int) $note->id;
    }

    /**
     * @param class-string $class
     */
    private function rawColumn(EntityManagerInterface $em, string $class, string $column, int $id): string
    {
        $raw = $em->getConnection()->fetchOne(
            sprintf('SELECT %s FROM %s WHERE id = ?', $column, $em->getClassMetadata($class)->getTableName()),
            [$id],
        );
        self::assertIsString($raw);

        return $raw;
    }

    /**
     * @param class-string<Throwable> $class
     */
    private static function chainContains(Throwable $e, string $class): bool
    {
        for (; $e instanceof Throwable; $e = $e->getPrevious()) {
            if ($e instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
