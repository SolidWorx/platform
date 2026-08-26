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

namespace SolidWorx\Platform\Tests\Bundle\Ui\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\UiBundle\Layout\LayoutOption;
use function count;

#[CoversClass(LayoutOption::class)]
final class LayoutOptionTest extends TestCase
{
    public function testDefaultsCoverEveryOption(): void
    {
        $defaults = LayoutOption::defaults();

        self::assertCount(count(LayoutOption::cases()), $defaults);

        foreach (LayoutOption::cases() as $option) {
            self::assertArrayHasKey($option->value, $defaults);
            self::assertSame($option->default(), $defaults[$option->value]);
        }
    }

    /**
     * The defaults are the Tabler defaults the layouts were built against; changing one silently
     * restyles every application, so they are pinned here.
     */
    public function testDefaultsMatchTheTablerBaseline(): void
    {
        self::assertSame([
            'theme' => null,
            'fluid' => false,
            'boxed' => false,
            'container' => null,
            'navbar' => true,
            'navbar_theme' => null,
            'navbar_sticky' => false,
            'navbar_overlap' => false,
            'navbar_expand' => 'md',
            'sidebar' => true,
            'sidebar_theme' => 'dark',
            'sidebar_position' => 'start',
            'sidebar_transparent' => false,
            'sidebar_expand' => 'lg',
            'page_header' => true,
            'footer' => true,
            'centered' => false,
        ], LayoutOption::defaults());
    }

    public function testEveryOptionAcceptsItsOwnDefault(): void
    {
        foreach (LayoutOption::cases() as $option) {
            self::assertTrue(
                $option->accepts($option->default()),
                $option->value . ' must accept its own default',
            );
        }
    }

    public function testEveryOptionIsDocumented(): void
    {
        foreach (LayoutOption::cases() as $option) {
            self::assertNotSame('', $option->description(), $option->value . ' must have a description');
            self::assertNotSame('', $option->type(), $option->value . ' must have a type');
        }
    }

    public function testPerPageOptionsAreNotApplicationConfigurable(): void
    {
        $configurable = LayoutOption::configurable();

        self::assertNotContains(LayoutOption::Container, $configurable);
        self::assertNotContains(LayoutOption::Centered, $configurable);
        self::assertContains(LayoutOption::Fluid, $configurable);
        self::assertCount(count(LayoutOption::cases()) - 2, $configurable);
    }

    #[DataProvider('typeProvider')]
    public function testTypeStringDescribesTheAcceptedValues(LayoutOption $option, string $expected): void
    {
        self::assertSame($expected, $option->type());
    }

    /**
     * @return iterable<string, array{LayoutOption, string}>
     */
    public static function typeProvider(): iterable
    {
        yield 'boolean' => [LayoutOption::Fluid, 'boolean'];
        yield 'nullable enum' => [LayoutOption::NavbarTheme, "null|'light'|'dark'"];
        yield 'enum' => [LayoutOption::NavbarExpand, "'sm'|'md'|'lg'|'xl'"];
        yield 'free-form string' => [LayoutOption::Container, 'string|null'];
    }

    #[DataProvider('acceptsProvider')]
    public function testAccepts(LayoutOption $option, mixed $value, bool $expected): void
    {
        self::assertSame($expected, $option->accepts($value));
    }

    /**
     * @return iterable<string, array{LayoutOption, mixed, bool}>
     */
    public static function acceptsProvider(): iterable
    {
        yield 'boolean takes a bool' => [LayoutOption::Fluid, true, true];
        yield 'boolean rejects a string' => [LayoutOption::Fluid, 'yes', false];
        yield 'boolean rejects null' => [LayoutOption::Fluid, null, false];
        yield 'enum takes a listed value' => [LayoutOption::NavbarExpand, 'lg', true];
        yield 'enum rejects an unlisted value' => [LayoutOption::NavbarExpand, 'xxl', false];
        yield 'nullable enum takes null' => [LayoutOption::SidebarTheme, null, true];
        yield 'container takes any string' => [LayoutOption::Container, 'container-md', true];
        yield 'container takes null' => [LayoutOption::Container, null, true];
        yield 'container rejects a bool' => [LayoutOption::Container, false, false];
    }
}
