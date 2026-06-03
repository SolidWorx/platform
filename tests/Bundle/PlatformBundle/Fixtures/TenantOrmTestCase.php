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

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Doctrine\Filter\TenantFilter;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Entity\TenantAwareItem;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use function str_contains;

/**
 * Base test case that boots an in-memory SQLite EntityManager mapped for {@see TenantAwareItem}
 * with the tenant filter registered.
 */
abstract class TenantOrmTestCase extends TestCase
{
    protected function createTenantEntityManager(?EventManager $eventManager = null): EntityManagerInterface
    {
        if (! Type::hasType(UlidType::NAME)) {
            Type::addType(UlidType::NAME, UlidType::class);
        }

        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/Entity'], true);
        $config->addFilter(TenantFilter::NAME, TenantFilter::class);

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $config);

        $entityManager = new EntityManager($connection, $config, $eventManager);

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema([$entityManager->getClassMetadata(TenantAwareItem::class)]);

        return $entityManager;
    }

    /**
     * Generates a ULID whose binary form contains no NUL byte.
     *
     * Symfony's ULID type stores ULIDs as 16-byte binary on SQLite but binds them as strings, which
     * SQLite truncates at the first NUL byte. Avoiding NUL bytes keeps the functional tests
     * deterministic without altering the production storage format.
     */
    protected function ulid(): Ulid
    {
        do {
            $ulid = new Ulid();
        } while (str_contains($ulid->toBinary(), "\0"));

        return $ulid;
    }
}
