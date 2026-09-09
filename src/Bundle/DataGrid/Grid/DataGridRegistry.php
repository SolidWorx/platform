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

use Psr\Container\ContainerInterface;
use SolidWorx\Platform\DataGridBundle\Exception\UnknownDataGridException;
use Symfony\Contracts\Service\ServiceProviderInterface;
use function array_keys;

/**
 * Resolves the name used in `<twig:Platform:DataGrid name="…" />` to the grid
 * service. The map is built by {@see \SolidWorx\Platform\DataGridBundle\DependencyInjection\CompilerPass\DataGridRegistryPass}.
 */
final readonly class DataGridRegistry
{
    /**
     * `ServiceLocator` (what the compiler pass, and the tests, construct this
     * with) does not propagate a template type from its factory array, so it
     * type-checks only as `ServiceProviderInterface<mixed>`. `get()` below
     * narrows the per-name result to {@see AbstractDataGrid} explicitly.
     *
     * @param ServiceProviderInterface<mixed>&ContainerInterface $grids
     */
    public function __construct(
        private ServiceProviderInterface & ContainerInterface $grids,
    ) {
    }

    /**
     * @throws UnknownDataGridException
     */
    public function get(string $name): AbstractDataGrid
    {
        if (! $this->grids->has($name)) {
            throw UnknownDataGridException::forName($name, $this->names());
        }

        /** @var AbstractDataGrid */
        return $this->grids->get($name);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->grids->getProvidedServices());
    }
}
