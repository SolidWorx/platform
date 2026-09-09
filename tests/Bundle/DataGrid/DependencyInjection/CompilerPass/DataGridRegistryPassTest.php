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

namespace SolidWorx\Platform\Tests\Bundle\DataGrid\DependencyInjection\CompilerPass;

use LogicException;
use Pentiminax\UX\DataTables\DependencyInjection\Compiler\DataTableRegistryPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\DataGridBundle\DependencyInjection\CompilerPass\DataGridRegistryPass;
use SolidWorx\Platform\DataGridBundle\Grid\DataGridRegistry;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid\ClientDataGrid;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid\NamedDataGrid;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use function array_keys;

#[CoversClass(DataGridRegistryPass::class)]
final class DataGridRegistryPassTest extends TestCase
{
    public function testTaggedGridsAreMappedByName(): void
    {
        $container = $this->containerWith([
            'app.client_grid' => ClientDataGrid::class,
            'app.named_grid' => NamedDataGrid::class,
        ]);

        new DataGridRegistryPass()->process($container);

        self::assertSame(['client', 'overridden'], array_keys($this->locatorMap($container)));
    }

    public function testDuplicateNamesAreRejected(): void
    {
        $container = $this->containerWith([
            'app.grid_one' => ClientDataGrid::class,
            'app.grid_two' => ClientDataGrid::class,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('Two data grids resolve to the name "client"');

        new DataGridRegistryPass()->process($container);
    }

    public function testNonGridDataTablesAreIgnored(): void
    {
        $container = $this->containerWith([
            'app.plain_table' => stdClass::class,
        ]);

        new DataGridRegistryPass()->process($container);

        self::assertSame([], $this->locatorMap($container));
    }

    /**
     * @param array<string, class-string> $services
     */
    private function containerWith(array $services): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(DataGridRegistry::class, new Definition(DataGridRegistry::class));

        foreach ($services as $id => $class) {
            $container->setDefinition($id, new Definition($class)->addTag(DataTableRegistryPass::TAG));
        }

        return $container;
    }

    /**
     * `Definition::getArgument()` returns `mixed`, so PHPStan sees both the
     * locator argument and the service map inside it as `mixed`. Narrow both
     * to their real shape in one place instead of casting at each call site.
     *
     * @return array<string, Reference>
     */
    private function locatorMap(ContainerBuilder $container): array
    {
        /** @var Definition $locator */
        $locator = $container->getDefinition(DataGridRegistry::class)->getArgument(0);

        /** @var array<string, Reference> */
        return $locator->getArgument(0);
    }
}
