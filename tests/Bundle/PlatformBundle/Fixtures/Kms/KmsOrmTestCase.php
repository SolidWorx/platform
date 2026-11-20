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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Kms;

use Closure;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\TypeRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\NamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use ReflectionClass;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\TenantOrmTestCase;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedTypes;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\DependencyInjection\RegisterEncryptedColumnsPass;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\EncryptedColumnListener;
use Symfony\Component\KeyManagement\EnvelopeDecrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeEncrypterInterface;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;
use function class_exists;
use function is_array;
use function is_string;
use function random_bytes;
use function sprintf;

/**
 * Boots an in-memory SQLite EntityManager with Symfony's {@see EncryptedColumnListener}, wired the
 * way RegisterEncryptedColumnsPass wires it in an application: explicit keys get the types collected
 * from the #[Encrypted] attributes, omitted keys get store-backed scopes from resolved metadata.
 *
 * Concrete tests need #[RequiresPhpExtension('sodium')] and #[RequiresPhpExtension('openssl')].
 */
abstract class KmsOrmTestCase extends TenantOrmTestCase
{
    /**
     * The store-backed encrypter of the last manager created, before any decoration.
     * Uninitialized until createKmsEntityManager() runs.
     */
    protected StoredEnvelopeEncrypter $encrypter;

    /**
     * The last manager created; null until createKmsEntityManager() returns.
     */
    protected ?EntityManagerInterface $entityManager = null;

    protected function setUp(): void
    {
        if (! class_exists(Encrypted::class)) {
            self::markTestSkipped('symfony/doctrine-orm-key-management is not installed.');
        }
    }

    /**
     * @param string $entityDir Directory of the entities to map
     * @param (Closure(StoredEnvelopeEncrypter): (EnvelopeEncrypterInterface&EnvelopeDecrypterInterface))|null $decorate
     *        Wraps the store-backed encrypter; what it returns encrypts every column, explicit and implicit.
     *        It runs before the manager exists: use managerRegistry() without an argument to reach it lazily.
     * @param array<string, array{type: string, key: string}> $configuredTypes Encrypted types declared by name, as in
     *        "key_management.doctrine_dbal.types", for columns mapped to them without #[Encrypted]
     */
    protected function createKmsEntityManager(string $entityDir, ?NamingStrategy $namingStrategy = null, ?Closure $decorate = null, array $configuredTypes = []): EntityManagerInterface
    {
        $this->entityManager = null;

        $config = ORMSetup::createAttributeMetadataConfiguration([$entityDir], true);
        // A registry of its own, so the encrypted types of one test never leak into another.
        $config->setTypeProvider(new TypeRegistry([
            UlidType::NAME => UlidType::class,
        ]));
        $config->enableNativeLazyObjects(true);

        if ($namingStrategy instanceof NamingStrategy) {
            $config->setNamingStrategy($namingStrategy);
        }

        $this->encrypter = new StoredEnvelopeEncrypter(new InMemoryDataKeyStore([
            'default' => new SodiumKms(new InMemoryKeyLoader([
                'app' => random_bytes(32),
            ])),
        ]));
        $envelopes = $decorate instanceof Closure ? $decorate($this->encrypter) : $this->encrypter;

        // Explicit keys: collected exactly as RegisterEncryptedColumnsPass does at compile time.
        $names = [];
        $types = $configuredTypes;

        foreach ($configuredTypes as $name => $configured) {
            $names[$configured['key']][$configured['type']] ??= $name;
        }

        $driver = $config->getMetadataDriverImpl();
        self::assertNotNull($driver);

        foreach ($driver->getAllClassNames() as $class) {
            $definition = new ChildDefinition('');
            RegisterEncryptedColumnsPass::autoconfigure($definition, new Entity(), new ReflectionClass($class));

            foreach ($definition->getTag('key_management.encrypted_column') as $tag) {
                $key = is_array($tag) ? $tag['key'] ?? null : null;
                $type = is_array($tag) ? $tag['type'] ?? null : null;

                // A null key is an implicit scope, left to the listener.
                if (! is_string($key) || ! is_string($type)) {
                    continue;
                }

                if (isset($names[$key][$type])) {
                    continue;
                }

                $name = sprintf('encrypted_%s@%s', $type, $key);
                $names[$key][$type] = $name;
                $types[$name] = [
                    'type' => $type,
                    'key' => $key,
                ];
            }
        }

        $listener = new EncryptedColumnListener($names, $envelopes);
        $eventManager = new EventManager();
        $eventManager->addEventListener(Events::loadClassMetadata, $listener);

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $config);

        new EncryptedTypes($envelopes, $types)->register($connection);

        $entityManager = new EntityManager($connection, $config, $eventManager);
        $listener->register($entityManager);

        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $this->entityManager = $entityManager;
    }

    /**
     * A registry over the given manager, or, without one, over the last manager created, resolved on
     * each call: it can be built inside the $decorate closure, before that manager exists.
     */
    protected function managerRegistry(?EntityManagerInterface $entityManager = null): ManagerRegistry
    {
        $registry = self::createStub(ManagerRegistry::class);
        $registry->method('getManagers')->willReturnCallback(fn (): array => [
            'default' => $entityManager ?? $this->entityManager ?? throw new LogicException('No entity manager has been created yet.'),
        ]);

        return $registry;
    }
}
