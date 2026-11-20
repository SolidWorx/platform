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
use Doctrine\ORM\Mapping\DefaultNamingStrategy;
use Doctrine\ORM\Mapping\NamingStrategy;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use ReflectionProperty;
use SolidWorx\Platform\PlatformBundle\Kms\Exception\ConflictingEncryptionScopeException;
use SolidWorx\Platform\PlatformBundle\Kms\TenantScopeRegistry;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Kms\Entity\EncryptedTenantChild;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Kms\Entity\EncryptedTenantNote;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Kms\Entity\EncryptedTenantSecret;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Kms\Entity\EncryptedUserProfile;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Kms\KmsOrmTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedType;
use function hash;
use function serialize;

#[CoversClass(TenantScopeRegistry::class)]
#[CoversClass(ConflictingEncryptionScopeException::class)]
#[RequiresPhpExtension('sodium')]
#[RequiresPhpExtension('openssl')]
final class TenantScopeRegistryTest extends KmsOrmTestCase
{
    private const string ENTITY_DIR = __DIR__ . '/../Fixtures/Kms/Entity';

    /**
     * @return iterable<string, array{NamingStrategy, string}>
     */
    public static function namingStrategies(): iterable
    {
        yield 'default' => [new DefaultNamingStrategy(), 'EncryptedTenantNote'];
        yield 'underscore' => [new UnderscoreNamingStrategy(), 'encrypted_tenant_note'];
    }

    #[DataProvider('namingStrategies')]
    public function testImplicitScopeUsesResolvedTableName(NamingStrategy $namingStrategy, string $table): void
    {
        $entityManager = $this->createKmsEntityManager(self::ENTITY_DIR, $namingStrategy);
        $registry = $this->registry($entityManager);

        // The oracle: the key of the type Symfony gave the column is what encrypt() receives.
        $scope = $this->encryptedTypeKey($entityManager, EncryptedTenantNote::class, 'body');

        $this->assertSame('orm:' . hash('sha256', serialize([null, $table, 'body'])), $scope);
        $this->assertTrue($registry->isTenantScope($scope));
    }

    public function testUnresolvedTableNameIsNotTenantScope(): void
    {
        $registry = $this->registry($this->createKmsEntityManager(self::ENTITY_DIR, new UnderscoreNamingStrategy()));

        $this->assertFalse($registry->isTenantScope('orm:' . hash('sha256', serialize([null, 'EncryptedTenantNote', 'body']))));
    }

    public function testExplicitKeyIsTenantScope(): void
    {
        $entityManager = $this->createKmsEntityManager(self::ENTITY_DIR);

        $this->assertSame('tenant.secret', $this->encryptedTypeKey($entityManager, EncryptedTenantSecret::class, 'secret'));
        $this->assertTrue($this->registry($entityManager)->isTenantScope('tenant.secret'));
    }

    public function testNonTenantEntityScopeIsNotTenantScope(): void
    {
        $entityManager = $this->createKmsEntityManager(self::ENTITY_DIR);
        $scope = $this->encryptedTypeKey($entityManager, EncryptedUserProfile::class, 'phone');

        $this->assertFalse($this->registry($entityManager)->isTenantScope($scope));
    }

    public function testInheritedEncryptedPropertyIsTenantScoped(): void
    {
        $entityManager = $this->createKmsEntityManager(self::ENTITY_DIR);
        $scope = $this->encryptedTypeKey($entityManager, EncryptedTenantChild::class, 'label');

        $this->assertTrue($this->registry($entityManager)->isTenantScope($scope));
    }

    public function testUnknownScopeIsNotTenantScope(): void
    {
        $this->assertFalse($this->registry($this->createKmsEntityManager(self::ENTITY_DIR))->isTenantScope('unknown'));
    }

    public function testScopeUsedByTenantAndNonTenantColumnThrows(): void
    {
        $registry = $this->registry($this->createKmsEntityManager(__DIR__ . '/../Fixtures/Kms/ConflictingKey'));

        $this->expectException(ConflictingEncryptionScopeException::class);
        $this->expectExceptionMessageIsOrContains('shared.key');

        $registry->isTenantScope('shared.key');
    }

    public function testJoinedInheritedColumnUsesParentTableScope(): void
    {
        // The tenant-aware child stores its inherited column in the non-tenant parent's table, so
        // both share that table's scope: refused rather than silently encrypted under a shared key.
        $registry = $this->registry($this->createKmsEntityManager(__DIR__ . '/../Fixtures/Kms/ConflictingJoined'));

        $this->expectException(ConflictingEncryptionScopeException::class);

        $registry->isTenantScope('anything');
    }

    public function testResultIsCached(): void
    {
        $entityManager = $this->createKmsEntityManager(self::ENTITY_DIR);
        $pool = new ArrayAdapter();

        $this->assertTrue(new TenantScopeRegistry($this->managerRegistry($entityManager), $pool)->isTenantScope('tenant.secret'));
        $this->assertTrue($pool->getItem('platform_kms_tenant_scopes')->isHit());

        $failing = self::createStub(ManagerRegistry::class);
        $failing->method('getManagers')->willThrowException(new LogicException('Metadata must not be loaded on a cache hit.'));

        $registry = new TenantScopeRegistry($failing, $pool);

        $this->assertTrue($registry->isTenantScope('tenant.secret'));
        $this->assertFalse($registry->isTenantScope('unknown'));
    }

    private function registry(EntityManagerInterface $entityManager): TenantScopeRegistry
    {
        return new TenantScopeRegistry($this->managerRegistry($entityManager), new ArrayAdapter());
    }

    /**
     * @param class-string $class
     */
    private function encryptedTypeKey(EntityManagerInterface $entityManager, string $class, string $field): string
    {
        $typeName = $entityManager->getClassMetadata($class)->getFieldMapping($field)->type;
        $type = $entityManager->getConnection()->getConfiguration()->getTypeProvider()->get($typeName);
        $this->assertInstanceOf(EncryptedType::class, $type);

        $key = new ReflectionProperty(EncryptedType::class, 'key')->getValue($type);
        $this->assertIsString($key);

        return $key;
    }
}
