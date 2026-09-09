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

namespace SolidWorx\Platform\DataGridBundle\Config\Builder;

use Webmozart\Assert\Assert;

/**
 * PHP fluent builder for the `datagrid:` configuration section.
 *
 * Usage:
 *
 *     use SolidWorx\Platform\DataGridBundle\Config\Builder\DataGridConfigBuilder;
 *
 *     return DataGridConfigBuilder::create()
 *         ->pageLength(50)
 *         ->ajaxAccess('ROLE_ADMIN')
 *         ->build();
 */
final class DataGridConfigBuilder
{
    private ?bool $enabled = null;

    private ?int $pageLength = null;

    /**
     * @var list<int>|null
     */
    private ?array $lengthMenu = null;

    private ?string $tableClass = null;

    private ?string $ajaxAccess = null;

    /**
     * @var list<'csv'|'xlsx'>|null
     */
    private ?array $exportFormats = null;

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function disabled(): self
    {
        $this->enabled = false;

        return $this;
    }

    public function pageLength(int $pageLength): self
    {
        Assert::greaterThan($pageLength, 0);

        $this->pageLength = $pageLength;

        return $this;
    }

    /**
     * @param list<int> $lengthMenu
     */
    public function lengthMenu(array $lengthMenu): self
    {
        Assert::allGreaterThan($lengthMenu, 0);

        $this->lengthMenu = $lengthMenu;

        return $this;
    }

    public function tableClass(string $tableClass): self
    {
        Assert::stringNotEmpty($tableClass);

        $this->tableClass = $tableClass;

        return $this;
    }

    public function ajaxAccess(string $attribute): self
    {
        Assert::stringNotEmpty($attribute);

        $this->ajaxAccess = $attribute;

        return $this;
    }

    /**
     * @param list<'csv'|'xlsx'> $formats
     */
    public function exportFormats(array $formats): self
    {
        Assert::allInArray($formats, ['csv', 'xlsx']);

        $this->exportFormats = $formats;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $config = [];

        if ($this->enabled !== null) {
            $config['enabled'] = $this->enabled;
        }

        if ($this->pageLength !== null) {
            $config['page_length'] = $this->pageLength;
        }

        if ($this->lengthMenu !== null) {
            $config['length_menu'] = $this->lengthMenu;
        }

        if ($this->tableClass !== null) {
            $config['table_class'] = $this->tableClass;
        }

        if ($this->ajaxAccess !== null) {
            $config['security'] = [
                'ajax_access' => $this->ajaxAccess,
            ];
        }

        if ($this->exportFormats !== null) {
            $config['export'] = [
                'formats' => $this->exportFormats,
            ];
        }

        return $config;
    }
}
