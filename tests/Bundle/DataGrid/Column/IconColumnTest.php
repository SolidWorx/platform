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

namespace SolidWorx\Platform\Tests\Bundle\DataGrid\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\DataGridBundle\Column\IconColumn;

#[CoversClass(IconColumn::class)]
final class IconColumnTest extends TestCase
{
    public function testUsesThePlatformTemplate(): void
    {
        self::assertSame(
            '@DataGrid/columns/icon.html.twig',
            IconColumn::new('status')->getTemplate(),
        );
    }

    public function testIconMapAndFallbackArePassedToTheTemplate(): void
    {
        $column = IconColumn::new('status')
            ->icons([
                'active' => 'tabler:circle-check',
            ])
            ->fallbackIcon('tabler:circle-x');

        self::assertSame(
            [
                'icons' => [
                    'active' => 'tabler:circle-check',
                ],
                'fallbackIcon' => 'tabler:circle-x',
            ],
            $column->getTemplateParameters(),
        );
    }

    public function testIsNotSearchableOrOrderable(): void
    {
        $column = IconColumn::new('status');

        self::assertFalse($column->isSearchable());
        self::assertFalse($column->isOrderable());
    }

    /**
     * Guards icons() in isolation. A combined assertion after both mutators
     * run would still pass even if icons() forgot to re-send the template
     * parameters, because fallbackIcon()'s later call re-sends whatever
     * `$this->icons` already holds. Asserting right after icons() alone, with
     * no later mutator to paper over a missing re-send, closes that gap.
     */
    public function testIconsAloneUpdatesTheTemplateParameters(): void
    {
        $column = IconColumn::new('status')->icons([
            'active' => 'tabler:circle-check',
        ]);

        self::assertSame(
            [
                'icons' => [
                    'active' => 'tabler:circle-check',
                ],
                'fallbackIcon' => null,
            ],
            $column->getTemplateParameters(),
        );
    }

    /**
     * Guards fallbackIcon() in isolation, for the same reason as
     * testIconsAloneUpdatesTheTemplateParameters() above.
     */
    public function testFallbackIconAloneUpdatesTheTemplateParameters(): void
    {
        $column = IconColumn::new('status')->fallbackIcon('tabler:circle-x');

        self::assertSame(
            [
                'icons' => [],
                'fallbackIcon' => 'tabler:circle-x',
            ],
            $column->getTemplateParameters(),
        );
    }
}
