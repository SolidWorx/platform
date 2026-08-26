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
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SolidWorx\Platform\UiBundle\Exception\InvalidLayoutOptionException;
use SolidWorx\Platform\UiBundle\Layout\LayoutOption;
use SolidWorx\Platform\UiBundle\Layout\LayoutResolver;
use SolidWorx\Platform\UiBundle\Twig\Runtime\LayoutRuntime;
use function array_map;
use function preg_replace;
use function strtolower;

#[CoversClass(LayoutRuntime::class)]
#[UsesClass(LayoutResolver::class)]
#[UsesClass(LayoutOption::class)]
#[UsesClass(InvalidLayoutOptionException::class)]
final class LayoutRuntimeTest extends TestCase
{
    /**
     * The whole point of `ui_layout()` is that Twig can match named arguments against this
     * signature — so the signature has to stay in step with the options, or a template would be
     * unable to set one.
     */
    public function testEveryOptionHasAMatchingParameter(): void
    {
        $parameters = array_map(
            static fn (string $name): string => strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name)),
            array_map(
                static fn (object $parameter): string => $parameter->getName(),
                new ReflectionMethod(LayoutRuntime::class, 'layout')->getParameters(),
            ),
        );

        self::assertSame(LayoutOption::names(), $parameters);
    }

    public function testOmittedArgumentsAreLeftOut(): void
    {
        self::assertSame([
            'fluid' => true,
        ], $this->runtime()->layout(fluid: true));
    }

    public function testNothingPassedYieldsAnEmptySet(): void
    {
        self::assertSame([], $this->runtime()->layout());
    }

    /**
     * `null` is a real value for the theme options — it means "follow the user preference" — so it
     * must survive rather than being treated as "not passed".
     */
    public function testExplicitNullIsKept(): void
    {
        self::assertSame([
            'sidebar_theme' => null,
        ], $this->runtime()->layout(sidebarTheme: null));
    }

    public function testFalseIsKept(): void
    {
        self::assertSame([
            'footer' => false,
        ], $this->runtime()->layout(footer: false));
    }

    public function testValuesAreValidatedAtTheCallSite(): void
    {
        $this->expectException(InvalidLayoutOptionException::class);
        $this->expectExceptionMessageIsOrContains('Layout option "sidebar_position" does not accept \'middle\'.');

        $this->runtime()->layout(sidebarPosition: 'middle');
    }

    public function testResolveMergesOntoTheApplicationDefaults(): void
    {
        $runtime = new LayoutRuntime(new LayoutResolver([
            'fluid' => true,
        ]));

        $resolved = $runtime->resolve([
            'navbar_sticky' => true,
        ]);

        self::assertTrue($resolved['fluid']);
        self::assertTrue($resolved['navbar_sticky']);
        self::assertSame('dark', $resolved['sidebar_theme']);
    }

    private function runtime(): LayoutRuntime
    {
        return new LayoutRuntime(new LayoutResolver());
    }
}
