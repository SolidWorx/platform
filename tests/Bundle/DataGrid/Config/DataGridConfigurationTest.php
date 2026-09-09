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

namespace SolidWorx\Platform\Tests\Bundle\DataGrid\Config;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\DataGridBundle\Config\DataGridConfiguration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @phpstan-import-type DataGridConfig from DataGridConfiguration
 */
#[CoversClass(DataGridConfiguration::class)]
final class DataGridConfigurationTest extends TestCase
{
    private DataGridConfiguration $configuration;

    private Processor $processor;

    #[Override]
    protected function setUp(): void
    {
        $this->configuration = new DataGridConfiguration();
        $this->processor = new Processor();
    }

    public function testGetConfigSectionKeyReturnsDatagrid(): void
    {
        self::assertSame('datagrid', $this->configuration->getConfigSectionKey());
    }

    public function testTreeBuilderRootNodeIsNamedDatagrid(): void
    {
        self::assertSame('datagrid', $this->configuration->getTreeBuilder()->buildTree()->getName());
    }

    public function testDefaults(): void
    {
        $config = $this->process([]);

        self::assertTrue($config['enabled']);
        self::assertSame(25, $config['page_length']);
        self::assertSame([10, 25, 50, 100], $config['length_menu']);
        self::assertTrue($config['responsive']);
        self::assertTrue($config['column_control']);
        self::assertSame('table table-vcenter card-table', $config['table_class']);
        self::assertSame('IS_AUTHENTICATED_FULLY', $config['security']['ajax_access']);
        self::assertTrue($config['export']['enabled']);
        self::assertSame(['csv', 'xlsx'], $config['export']['formats']);
        self::assertTrue($config['edit_modal']['enabled']);
    }

    public function testValuesCanBeOverridden(): void
    {
        $config = $this->process([
            'enabled' => false,
            'page_length' => 50,
            'length_menu' => [50, 100],
            'table_class' => 'table',
            'security' => [
                'ajax_access' => 'ROLE_ADMIN',
            ],
            'export' => [
                'formats' => ['csv'],
            ],
        ]);

        self::assertFalse($config['enabled']);
        self::assertSame(50, $config['page_length']);
        self::assertSame([50, 100], $config['length_menu']);
        self::assertSame('table', $config['table_class']);
        self::assertSame('ROLE_ADMIN', $config['security']['ajax_access']);
        self::assertSame(['csv'], $config['export']['formats']);
    }

    public function testPageLengthMustBePositive(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([
            'page_length' => 0,
        ]);
    }

    public function testAjaxAccessCannotBeEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([
            'security' => [
                'ajax_access' => '',
            ],
        ]);
    }

    public function testUnknownExportFormatIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([
            'export' => [
                'formats' => ['pdf'],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return DataGridConfig
     */
    private function process(array $values): array
    {
        /** @var DataGridConfig */
        return $this->processor->process($this->configuration->getTreeBuilder()->buildTree(), [$values]);
    }
}
