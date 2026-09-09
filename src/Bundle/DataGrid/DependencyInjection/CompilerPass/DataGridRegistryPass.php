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

namespace SolidWorx\Platform\DataGridBundle\DependencyInjection\CompilerPass;

use LogicException;
use Override;
use Pentiminax\UX\DataTables\DependencyInjection\Compiler\DataTableRegistryPass;
use SolidWorx\Platform\DataGridBundle\Grid\AbstractDataGrid;
use SolidWorx\Platform\DataGridBundle\Grid\DataGridRegistry;
use SolidWorx\Platform\DataGridBundle\Grid\GridNameResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use function is_a;
use function sprintf;

/**
 * Maps every registered grid to the name it is rendered by.
 *
 * Reuses upstream's `datatables.data_table` tag rather than a tag of our own:
 * the DataTables bundle already autoconfigures it onto every AbstractDataTable
 * subclass, so a grid would otherwise need two tags to work.
 */
final class DataGridRegistryPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasDefinition(DataGridRegistry::class)) {
            return;
        }

        $references = [];
        $classesByName = [];

        foreach ($container->findTaggedServiceIds(DataTableRegistryPass::TAG) as $id => $tags) {
            $class = $container->getDefinition($id)->getClass() ?? $id;

            if (! is_a($class, AbstractDataGrid::class, true)) {
                continue;
            }

            $name = GridNameResolver::resolve($class);

            if (isset($classesByName[$name])) {
                throw new LogicException(sprintf(
                    'Two data grids resolve to the name "%s": "%s" and "%s". Give one of them a NAME constant.',
                    $name,
                    $classesByName[$name],
                    $class,
                ));
            }

            $classesByName[$name] = $class;
            $references[$name] = new Reference($id);
        }

        $locator = new Definition(ServiceLocator::class)
            ->setArguments([$references])
            ->addTag('container.service_locator');

        $container->getDefinition(DataGridRegistry::class)->setArgument(0, $locator);
    }
}
