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

namespace SolidWorx\Platform\DataGridBundle\Grid;

use LogicException;
use Override;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Enum\StyleFramework;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableExtensions;
use Pentiminax\UX\DataTables\Model\Extensions\Button;
use ReflectionClass;
use Symfony\Component\ExpressionLanguage\Expression;
use function array_map;
use function sprintf;

/**
 * Base class for every platform data grid.
 *
 * Applies the platform's opinionated DataTables defaults, and falls back to
 * Doctrine metadata when a grid declares no columns. Every default is
 * overridable by implementing the matching configure*() hook.
 */
abstract class AbstractDataGrid extends AbstractDataTable
{
    private ?DataGridDefaults $defaults = null;

    final public function setDataGridDefaults(DataGridDefaults $defaults): void
    {
        $this->defaults = $defaults;
    }

    /**
     * The attribute a user must be granted to read this grid over the Ajax
     * routes. Null means the bundle-wide `datagrid.security.ajax_access` check
     * is the only gate.
     */
    public function getSecurityAttribute(): string|Expression|null
    {
        return null;
    }

    #[Override]
    public function configureDataTable(DataTable $table): DataTable
    {
        $defaults = $this->defaults();

        return $table
            // Required: RenderingPreparer only wires the Ajax URL and table
            // token for a server-side table.
            ->serverSide()
            // Explicit, because detectStyleFramework() sniffs stylesheet hrefs
            // and Encore compiles everything into one bundle, so it would fall
            // back to the plain DataTables theme.
            ->styleFramework(StyleFramework::Bootstrap5)
            ->pageLength($defaults->pageLength)
            ->lengthMenu($defaults->lengthMenu)
            ->setAttributes(['class' => $defaults->tableClass]);
    }

    #[Override]
    public function configureExtensions(DataTableExtensions $extensions): DataTableExtensions
    {
        $defaults = $this->defaults();

        if ($defaults->responsive) {
            $extensions->addResponsiveExtension();
        }

        if ($defaults->columnControl) {
            $extensions->addColumnControlExtension();
        }

        if ($defaults->exportEnabled && $defaults->exportFormats !== []) {
            $extensions->addButtonsExtension(array_map(
                // Server-side: the export streams every filtered row from PHP
                // rather than the page DataTables currently holds.
                static fn (string $format): Button => match ($format) {
                    'csv' => Button::csv(serverSide: true),
                    'xlsx' => Button::excel(serverSide: true),
                    default => throw new LogicException(sprintf('Unsupported export format "%s".', $format)),
                },
                $defaults->exportFormats,
            ));
        }

        return $extensions;
    }

    #[Override]
    public function configureColumns(): iterable
    {
        $columns = parent::configureColumns();

        if ($columns !== []) {
            return $columns;
        }

        $entityClass = $this->resolveEntityClass();

        if ($entityClass === null) {
            return [];
        }

        return $this->defaults()->columnFactory->createForEntity($entityClass);
    }

    /**
     * Read the entity class straight off the attribute.
     *
     * Deliberately not getEntityClass(): configureColumns() runs from inside
     * AbstractDataTable::initialize(), which only sets its initialised flag
     * once it returns, and getEntityClass() calls initialize() itself — so
     * using it here recurses until the stack overflows.
     *
     * @return class-string|null
     */
    private function resolveEntityClass(): ?string
    {
        $attributes = new ReflectionClass(static::class)->getAttributes(AsDataTable::class);

        if ($attributes === []) {
            return null;
        }

        /** @var class-string */
        return $attributes[0]->newInstance()->entityClass;
    }

    private function defaults(): DataGridDefaults
    {
        return $this->defaults ?? throw new LogicException(sprintf(
            'The data grid defaults were never injected into "%s". Grids must be registered as services so that the container can call setDataGridDefaults().',
            static::class,
        ));
    }
}
