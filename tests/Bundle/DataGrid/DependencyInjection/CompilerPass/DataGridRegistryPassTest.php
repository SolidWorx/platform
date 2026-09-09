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
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid\DuplicateNamedDataGrid;
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
        // Two DISTINCT classes that collide on name (both resolve to
        // "overridden") -- not the same class registered twice under two
        // ids. Registering the same class twice would make $classesByName[$name]
        // and $class the same string, so the test could not tell a correct
        // "first class, second class" message apart from a bug that prints
        // one of them twice.
        $container = $this->containerWith([
            'app.grid_one' => NamedDataGrid::class,
            'app.grid_two' => DuplicateNamedDataGrid::class,
        ]);

        try {
            new DataGridRegistryPass()->process($container);
            self::fail('Expected a LogicException for the colliding "overridden" name.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('Two data grids resolve to the name "overridden"', $exception->getMessage());
            self::assertStringContainsString(NamedDataGrid::class, $exception->getMessage());
            self::assertStringContainsString(DuplicateNamedDataGrid::class, $exception->getMessage());
        }
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
