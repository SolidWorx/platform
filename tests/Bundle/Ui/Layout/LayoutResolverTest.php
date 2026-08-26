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
use SolidWorx\Platform\UiBundle\Exception\InvalidLayoutOptionException;
use SolidWorx\Platform\UiBundle\Layout\LayoutOption;
use SolidWorx\Platform\UiBundle\Layout\LayoutResolver;

#[CoversClass(LayoutResolver::class)]
#[UsesClass(LayoutOption::class)]
#[UsesClass(InvalidLayoutOptionException::class)]
final class LayoutResolverTest extends TestCase
{
    public function testResolvingNothingYieldsTheDefaults(): void
    {
        self::assertSame(LayoutOption::defaults(), new LayoutResolver()->resolve());
    }

    public function testApplicationDefaultsOverrideTheBuiltInDefaults(): void
    {
        $resolved = new LayoutResolver([
            'navbar_sticky' => true,
            'sidebar_theme' => 'light',
        ])->resolve();

        self::assertTrue($resolved['navbar_sticky']);
        self::assertSame('light', $resolved['sidebar_theme']);
        self::assertFalse($resolved['fluid'], 'untouched options keep their default');
    }

    public function testTemplateOptionsOverrideTheApplicationDefaults(): void
    {
        $resolved = new LayoutResolver([
            'navbar_sticky' => true,
            'fluid' => true,
        ])->resolve([
            'navbar_sticky' => false,
        ]);

        self::assertFalse($resolved['navbar_sticky'], 'the template wins');
        self::assertTrue($resolved['fluid'], 'the application default still applies');
    }

    public function testResolvingAlwaysReturnsEveryOption(): void
    {
        $resolved = new LayoutResolver()->resolve([
            'fluid' => true,
        ]);

        foreach (LayoutOption::names() as $name) {
            self::assertArrayHasKey($name, $resolved);
        }
    }

    public function testApplicationDefaultsIgnoreKeysThatAreNotOptions(): void
    {
        $resolved = new LayoutResolver([
            'not_an_option' => true,
        ])->resolve();

        self::assertArrayNotHasKey('not_an_option', $resolved);
    }

    public function testUnknownTemplateOptionIsRejectedWithASuggestion(): void
    {
        $this->expectException(InvalidLayoutOptionException::class);
        $this->expectExceptionMessageIsOrContains('Unknown layout option "navbar_stick". Did you mean "navbar_sticky"?');

        new LayoutResolver()->resolve([
            'navbar_stick' => true,
        ]);
    }

    public function testUnrelatedOptionNameListsTheAvailableOptions(): void
    {
        $this->expectException(InvalidLayoutOptionException::class);
        $this->expectExceptionMessageIsOrContains('Available options are: theme, fluid,');

        new LayoutResolver()->resolve([
            'completely_made_up' => true,
        ]);
    }

    public function testValueOutsideTheAllowedSetIsRejected(): void
    {
        $this->expectException(InvalidLayoutOptionException::class);
        $this->expectExceptionMessageIsOrContains('Layout option "navbar_expand" does not accept \'xxl\'. Expected "sm", "md", "lg", "xl".');

        new LayoutResolver()->resolve([
            'navbar_expand' => 'xxl',
        ]);
    }

    public function testWrongTypeIsRejected(): void
    {
        $this->expectException(InvalidLayoutOptionException::class);
        $this->expectExceptionMessageIsOrContains('Layout option "fluid" does not accept \'yes\'. Expected boolean.');

        new LayoutResolver()->resolve([
            'fluid' => 'yes',
        ]);
    }

    public function testValidateReturnsOnlyWhatWasGiven(): void
    {
        self::assertSame([
            'fluid' => true,
            'sidebar_theme' => null,
        ], new LayoutResolver()->validate([
            'fluid' => true,
            'sidebar_theme' => null,
        ]));
    }
}
