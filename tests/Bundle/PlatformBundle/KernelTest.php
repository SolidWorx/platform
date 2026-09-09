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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use SolidWorx\Platform\PlatformBundle\Kernel;

#[CoversClass(Kernel::class)]
final class KernelTest extends TestCase
{
    /**
     * @param array<string, mixed> $rawConfig
     */
    #[DataProvider('dataGridEnabledProvider')]
    public function testIsDataGridEnabled(array $rawConfig, bool $expected): void
    {
        $kernel = new DataGridKernelFixture('test', false);

        // Reflected on the declaring class, not the fixture subclass: a private property is
        // not found by name when reflecting a child class in current PHP.
        $rawConfigProperty = new ReflectionClass(Kernel::class)->getProperty('rawConfig');
        $rawConfigProperty->setValue($kernel, $rawConfig);

        $isDataGridEnabled = new ReflectionMethod($kernel, 'isDataGridEnabled');

        self::assertSame($expected, $isDataGridEnabled->invoke($kernel));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function dataGridEnabledProvider(): iterable
    {
        yield 'section absent' => [[], true];
        yield 'section not an array' => [[
            'datagrid' => 'oops',
        ], true];
        yield 'enabled absent' => [[
            'datagrid' => [],
        ], true];
        yield 'enabled true' => [[
            'datagrid' => [
                'enabled' => true,
            ],
        ], true];
        yield 'enabled false' => [[
            'datagrid' => [
                'enabled' => false,
            ],
        ], false];
        yield 'enabled string "true"' => [[
            'datagrid' => [
                'enabled' => 'true',
            ],
        ], true];
        yield 'enabled string "false"' => [[
            'datagrid' => [
                'enabled' => 'false',
            ],
        ], false];
        yield 'enabled int 1' => [[
            'datagrid' => [
                'enabled' => 1,
            ],
        ], true];
    }
}

/**
 * Minimal concrete subclass; {@see Kernel} has no unimplemented abstract members of its
 * own, but is itself declared abstract so consuming applications must name their own kernel.
 */
final class DataGridKernelFixture extends Kernel
{
}
