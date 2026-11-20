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

namespace SolidWorx\Platform\PlatformBundle\Kms;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeProvider;
use Doctrine\DBAL\Types\TypeRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionProperty;
use SolidWorx\Platform\PlatformBundle\Kms\Exception\ConflictingEncryptionScopeException;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantAwareInterface;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedType;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;
use function array_map;
use function hash;
use function is_array;
use function method_exists;
use function serialize;
use function sprintf;

/**
 * Tells whether an encryption scope belongs to a column of a {@see TenantAwareInterface} entity.
 *
 * A scope is the key that Symfony's EncryptedType passes to the encrypter: the key of the
 * #[Encrypted] attribute, or the scope that EncryptedColumnListener derives from the physical
 * schema, table and column when the key is omitted. Both are computed here the way that listener
 * computes them, from resolved ORM metadata, so a renamed table or a naming strategy is accounted for.
 */
final class TenantScopeRegistry
{
    private const string CACHE_KEY = 'platform_kms_tenant_scopes';

    /**
     * @var array<string, true>|null
     */
    private ?array $tenantScopes = null;

    /**
     * @param CacheItemPoolInterface|null $cache Null in debug, where a mapping change must show up without a cache clear
     */
    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly ?CacheItemPoolInterface $cache = null,
    ) {
    }

    /**
     * @throws ConflictingEncryptionScopeException If a scope is used by both a tenant-aware column and one that is not
     */
    public function isTenantScope(string $scope): bool
    {
        $this->tenantScopes ??= $this->load();

        return isset($this->tenantScopes[$scope]);
    }

    /**
     * @return array<string, true>
     */
    private function load(): array
    {
        if (! $this->cache instanceof CacheItemPoolInterface) {
            return $this->resolve();
        }

        $item = $this->cache->getItem(self::CACHE_KEY);

        if ($item->isHit()) {
            $scopes = $item->get();

            if (is_array($scopes)) {
                /** @var array<string, true> $scopes */
                return $scopes;
            }
        }

        $scopes = $this->resolve();
        $this->cache->save($item->set($scopes));

        return $scopes;
    }

    /**
     * The registry EncryptedTypes::register() declares the types on.
     */
    private function typeProvider(EntityManagerInterface $manager): TypeProvider | TypeRegistry
    {
        $configuration = $manager->getConnection()->getConfiguration();

        // Configuration::getTypeProvider() arrived in DBAL 4.6; the bridge supports DBAL 4.3 and up.
        // @phpstan-ignore function.alreadyNarrowedType (DBAL < 4.6 compatibility)
        if (method_exists($configuration, 'getTypeProvider')) {
            return $configuration->getTypeProvider();
        }

        /** @phpstan-ignore staticMethod.deprecated (DBAL < 4.6 compatibility) */
        return Type::getTypeRegistry();
    }

    /**
     * @return array<string, true>
     */
    private function resolve(): array
    {
        /** @var array<string, class-string> $tenant */
        $tenant = [];
        /** @var array<string, class-string> $shared */
        $shared = [];

        foreach ($this->doctrine->getManagers() as $manager) {
            if (! $manager instanceof EntityManagerInterface) {
                continue;
            }

            foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
                if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass) {
                    continue;
                }

                $isTenantAware = $metadata->getReflectionClass()->implementsInterface(TenantAwareInterface::class);

                foreach ($this->scopes($metadata, $manager, $isTenantAware) as $scope) {
                    if ($isTenantAware) {
                        $tenant[$scope] ??= $metadata->getName();
                    } else {
                        $shared[$scope] ??= $metadata->getName();
                    }
                }
            }
        }

        foreach ($tenant as $scope => $class) {
            if (isset($shared[$scope])) {
                throw new ConflictingEncryptionScopeException($scope, $class, $shared[$scope]);
            }
        }

        return array_map(static fn (): true => true, $tenant);
    }

    /**
     * Mirrors EncryptedColumnListener::automaticTypes() for omitted keys.
     *
     * A tenant-aware column typed directly with an encrypted type, without #[Encrypted], is refused:
     * its key cannot be told apart from a shared one, and it would silently be encrypted as shared.
     *
     * @param ClassMetadata<object> $metadata
     *
     * @return list<string>
     */
    private function scopes(ClassMetadata $metadata, EntityManagerInterface $manager, bool $isTenantAware): array
    {
        $scopes = [];
        $types = null;

        foreach ($metadata->fieldMappings as $mapping) {
            /** @var class-string $class */
            $class = $mapping->originalClass ?? $mapping->declared ?? $metadata->getName();
            $property = new ReflectionProperty($class, $mapping->originalField ?? $mapping->fieldName);
            $attribute = $property->getAttributes(Encrypted::class)[0] ?? null;

            if ($attribute === null) {
                if ($isTenantAware) {
                    $types ??= $this->typeProvider($manager);

                    if ($types->has($mapping->type) && $types->get($mapping->type) instanceof EncryptedType) {
                        throw new LogicException(sprintf('The column "%s::$%s" of a tenant-aware entity uses the encrypted type "%s" directly. Use the #[%s] attribute instead, so the column is encrypted with the key of its tenant.', $metadata->getName(), $mapping->fieldName, $mapping->type, Encrypted::class));
                    }
                }

                continue;
            }

            $key = $attribute->newInstance()->key;

            if ($key !== null) {
                $scopes[] = $key;

                continue;
            }

            $owner = $metadata->isInheritanceTypeJoined() && $mapping->inherited !== null
                ? $manager->getMetadataFactory()->getMetadataFor($mapping->inherited)
                : $metadata;

            $scopes[] = 'orm:' . hash('sha256', serialize([$owner->getSchemaName(), $owner->getTableName(), $mapping->columnName]));
        }

        return $scopes;
    }
}
