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

use function constant;
use function defined;
use function preg_replace;
use function strrpos;
use function strtolower;
use function substr;

/**
 * Derives the name a grid is addressed by in `<twig:Platform:DataGrid name="…" />`.
 *
 * The class name is the name: `ClientDataGrid` is `client`, `ClientsDataGrid`
 * is `clients`. A `NAME` constant on the grid overrides it.
 */
final class GridNameResolver
{
    private const string NAME_CONSTANT = 'NAME';

    /**
     * @param class-string<AbstractDataGrid> $class
     */
    public static function resolve(string $class): string
    {
        if (defined($class . '::' . self::NAME_CONSTANT)) {
            /** @var string $name */
            $name = constant($class . '::' . self::NAME_CONSTANT);

            return $name;
        }

        $position = strrpos($class, '\\');

        return self::fromShortName($position === false ? $class : substr($class, $position + 1));
    }

    public static function fromShortName(string $shortName): string
    {
        $shortName = (string) preg_replace('/(DataGrid|Grid)$/', '', $shortName);

        // Split on the boundary between a run of capitals and a following word
        // (APIKey -> API_Key), then on every remaining lower-to-upper boundary.
        $snake = (string) preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $shortName);
        $snake = (string) preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $snake);

        return strtolower($snake);
    }
}
