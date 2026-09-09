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

namespace SolidWorx\Platform\Tests\Bundle\DataGrid\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\DataGridBundle\Grid\GridNameResolver;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid\ClientDataGrid;
use SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Grid\NamedDataGrid;

#[CoversClass(GridNameResolver::class)]
final class GridNameResolverTest extends TestCase
{
    public function testDerivesTheNameFromTheClass(): void
    {
        self::assertSame('client', GridNameResolver::resolve(ClientDataGrid::class));
    }

    public function testAConstantOverridesTheDerivedName(): void
    {
        self::assertSame('overridden', GridNameResolver::resolve(NamedDataGrid::class));
    }

    #[DataProvider('classNames')]
    public function testDerivation(string $shortName, string $expected): void
    {
        self::assertSame($expected, GridNameResolver::fromShortName($shortName));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function classNames(): iterable
    {
        yield 'suffix stripped' => ['ClientDataGrid', 'client'];
        yield 'plural preserved' => ['ClientsDataGrid', 'clients'];
        yield 'multi word snake cased' => ['InvoiceLineDataGrid', 'invoice_line'];
        yield 'grid suffix stripped' => ['InvoiceGrid', 'invoice'];
        yield 'no suffix' => ['Invoice', 'invoice'];
        yield 'acronym' => ['APIKeyDataGrid', 'api_key'];
    }
}
