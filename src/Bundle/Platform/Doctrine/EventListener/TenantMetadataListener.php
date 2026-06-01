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

namespace SolidWorx\Platform\PlatformBundle\Doctrine\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantAwareInterface;
use function array_filter;
use function array_merge;
use function array_values;
use function in_array;
use function sprintf;

/**
 * Enforces tenant indexing conventions on every {@see TenantAwareInterface} entity:
 *
 *  - a standalone index on the `tenant_id` column is added when none exists, so tenant-scoped
 *    queries are always indexed without per-entity boilerplate;
 *  - any composite index or unique constraint that includes the `tenant_id` column is reordered so
 *    `tenant_id` is the leading column, which is optimal for tenant-scoped lookups.
 *
 * Reordering index columns is safe: column order is a read-time optimization and does not affect
 * uniqueness semantics (a unique constraint is defined over the set of columns).
 */
#[AsDoctrineListener(event: Events::loadClassMetadata)]
final class TenantMetadataListener
{
    private const string FIELD = 'tenantId';

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();

        if ($metadata->reflClass?->implementsInterface(TenantAwareInterface::class) !== true) {
            return;
        }

        if (! $metadata->hasField(self::FIELD)) {
            return;
        }

        $column = $metadata->getColumnName(self::FIELD);

        /** @var array<string, array{columns: list<string>}> $indexes */
        $indexes = $metadata->table['indexes'] ?? [];
        /** @var array<string, array{columns: list<string>}> $uniqueConstraints */
        $uniqueConstraints = $metadata->table['uniqueConstraints'] ?? [];

        foreach ($indexes as $name => $definition) {
            $indexes[$name]['columns'] = $this->lead($definition['columns'] ?? [], $column);
        }

        foreach ($uniqueConstraints as $name => $definition) {
            $uniqueConstraints[$name]['columns'] = $this->lead($definition['columns'] ?? [], $column);
        }

        if (! $this->hasStandaloneIndex($indexes, $column)) {
            $indexes[sprintf('idx_%s_tenant', $metadata->getTableName())] = [
                'columns' => [$column],
            ];
        }

        $metadata->setPrimaryTable([
            'indexes' => $indexes,
            'uniqueConstraints' => $uniqueConstraints,
        ]);
    }

    /**
     * @param list<string> $columns
     *
     * @return list<string>
     */
    private function lead(array $columns, string $column): array
    {
        if ($columns === [] || ! in_array($column, $columns, true) || $columns[0] === $column) {
            return $columns;
        }

        return array_merge([$column], array_values(array_filter($columns, static fn (string $c): bool => $c !== $column)));
    }

    /**
     * @param array<string, array{columns: list<string>}> $indexes
     */
    private function hasStandaloneIndex(array $indexes, string $column): bool
    {
        return array_any($indexes, fn($definition): bool => ($definition['columns'] ?? []) === [$column]);
    }
}
