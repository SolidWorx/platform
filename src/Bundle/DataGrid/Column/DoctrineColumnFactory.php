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

namespace SolidWorx\Platform\DataGridBundle\Column;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\PropertyNameHumanizer;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Stringable;
use function ucfirst;

/**
 * Builds a column list from Doctrine mapping metadata.
 *
 * Upstream auto-detects columns only from #[Column] attributes on the entity or
 * from API Platform metadata, so a plain Doctrine entity would otherwise render
 * no columns at all.
 */
final readonly class DoctrineColumnFactory
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param class-string $entityClass
     *
     * @return list<AbstractColumn>
     */
    public function createForEntity(string $entityClass): array
    {
        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $humanizer = new PropertyNameHumanizer();

        $columns = [];

        foreach ($metadata->getFieldNames() as $fieldName) {
            if (! $this->isReadable($metadata, $fieldName)) {
                continue;
            }

            $column = $this->createFieldColumn($metadata, $fieldName, $humanizer->humanize($fieldName));

            if ($metadata->isIdentifier($fieldName)) {
                // An identifier is useful to display and to order by, but a
                // free-text search across it produces no meaningful matches.
                $column->setSearchable(false);
            }

            $columns[] = $column;
        }

        foreach ($metadata->getAssociationNames() as $associationName) {
            if (! $metadata->isSingleValuedAssociation($associationName)) {
                // A collection cannot be ordered or searched in one statement.
                continue;
            }

            if (! $this->isReadable($metadata, $associationName)) {
                continue;
            }

            $targetClass = $metadata->getAssociationTargetClass($associationName);

            if (! is_a($targetClass, Stringable::class, true)) {
                continue;
            }

            $columns[] = TextColumn::new($associationName, $humanizer->humanize($associationName));
        }

        return $columns;
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function createFieldColumn(ClassMetadata $metadata, string $fieldName, string $title): AbstractColumn
    {
        return match ($metadata->getTypeOfField($fieldName)) {
            Types::BOOLEAN => BooleanColumn::new($fieldName, $title),
            Types::INTEGER, Types::SMALLINT, Types::BIGINT, Types::FLOAT => NumberColumn::new($fieldName, $title),
            Types::DECIMAL => MoneyColumn::new($fieldName, $title),
            Types::DATE_MUTABLE, Types::DATE_IMMUTABLE,
            Types::DATETIME_MUTABLE, Types::DATETIME_IMMUTABLE,
            Types::DATETIMETZ_MUTABLE, Types::DATETIMETZ_IMMUTABLE,
            Types::TIME_MUTABLE, Types::TIME_IMMUTABLE => DateColumn::new($fieldName, $title),
            default => TextColumn::new($fieldName, $title),
        };
    }

    /**
     * The default row mapper reads values through the property accessor, so a
     * field with no public reader would map to null on every row.
     *
     * @param ClassMetadata<object> $metadata
     */
    private function isReadable(ClassMetadata $metadata, string $property): bool
    {
        $reflection = $metadata->getReflectionClass();

        foreach (['get' . ucfirst($property), 'is' . ucfirst($property), 'has' . ucfirst($property), $property] as $method) {
            if ($reflection->hasMethod($method) && $reflection->getMethod($method)->isPublic()) {
                return true;
            }
        }

        return $reflection->hasProperty($property) && $reflection->getProperty($property)->isPublic();
    }
}
