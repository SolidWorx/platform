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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Doctrine\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Doctrine\EventListener\TenantMetadataListener;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Entity\TenantAwareItem;
use stdClass;
use function is_array;

#[CoversClass(TenantMetadataListener::class)]
final class TenantMetadataListenerTest extends TestCase
{
    public function testAddsTenantIndexAndLeadsCompositeIndex(): void
    {
        $metadata = new ClassMetadata(TenantAwareItem::class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        $metadata->mapField([
            'fieldName' => 'tenantId',
            'type' => 'ulid',
            'columnName' => 'tenant_id',
            'nullable' => true,
        ]);
        $metadata->setPrimaryTable([
            'name' => 'tenant_aware_item',
            'indexes' => [
                'idx_item_name_tenant' => [
                    'columns' => ['name', 'tenant_id'],
                ],
            ],
        ]);

        $this->listenerFor($metadata);

        $indexes = $metadata->table['indexes'] ?? [];
        $this->assertIsArray($indexes);

        // The composite index now leads with tenant_id.
        $this->assertArrayHasKey('idx_item_name_tenant', $indexes);
        $composite = $indexes['idx_item_name_tenant'];
        $this->assertIsArray($composite);
        $this->assertSame(['tenant_id', 'name'], $composite['columns'] ?? null);

        // A standalone tenant_id index was added.
        $standalone = 0;
        foreach ($indexes as $definition) {
            if (is_array($definition) && ($definition['columns'] ?? null) === ['tenant_id']) {
                ++$standalone;
            }
        }

        $this->assertSame(1, $standalone);
    }

    public function testIgnoresNonTenantAwareEntities(): void
    {
        $metadata = new ClassMetadata(stdClass::class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        $metadata->setPrimaryTable([
            'name' => 'plain',
        ]);

        $this->listenerFor($metadata);

        $this->assertArrayNotHasKey('indexes', $metadata->table);
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function listenerFor(ClassMetadata $metadata): void
    {
        $event = self::createStub(LoadClassMetadataEventArgs::class);
        $event->method('getClassMetadata')->willReturn($metadata);

        new TenantMetadataListener()->loadClassMetadata($event);
    }
}
