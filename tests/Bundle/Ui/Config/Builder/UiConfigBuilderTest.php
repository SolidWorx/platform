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

namespace SolidWorx\Platform\Tests\Bundle\Ui\Config\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\UiBundle\Config\Builder\UiConfigBuilder;

#[CoversClass(UiConfigBuilder::class)]
final class UiConfigBuilderTest extends TestCase
{
    public function testDefaultsAreApplied(): void
    {
        $result = UiConfigBuilder::create()->build();

        self::assertSame('tabler', $result['icon_pack']);
        self::assertSame('@Ui/Layout/base.html.twig', self::section($result, 'templates')['base']);
        self::assertSame('@Ui/Security/login.html.twig', self::section($result, 'templates')['login']);
    }

    public function testIconPackCanBeOverridden(): void
    {
        $result = UiConfigBuilder::create()->iconPack('bootstrap')->build();

        self::assertSame('bootstrap', $result['icon_pack']);
    }

    public function testBaseTemplateCanBeOverridden(): void
    {
        $result = UiConfigBuilder::create()->baseTemplate('@App/layout/base.html.twig')->build();

        self::assertSame('@App/layout/base.html.twig', self::section($result, 'templates')['base']);
    }

    public function testLoginTemplateCanBeOverridden(): void
    {
        $result = UiConfigBuilder::create()->loginTemplate('@App/security/login.html.twig')->build();

        self::assertSame('@App/security/login.html.twig', self::section($result, 'templates')['login']);
    }

    public function testTemplatesAreNestedCorrectly(): void
    {
        $result = UiConfigBuilder::create()
            ->baseTemplate('@App/layout/base.html.twig')
            ->loginTemplate('@App/security/login.html.twig')
            ->build();

        self::assertArrayHasKey('templates', $result);
        self::assertArrayHasKey('base', self::section($result, 'templates'));
        self::assertArrayHasKey('login', self::section($result, 'templates'));
    }

    /**
     * Walk a nested key path, asserting each step is an array, and return the sub-array.
     *
     * @param array<array-key, mixed> $result
     * @return array<array-key, mixed>
     */
    private static function section(array $result, string ...$keys): array
    {
        $current = $result;
        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $current);
            $value = $current[$key];
            self::assertIsArray($value);
            $current = $value;
        }

        return $current;
    }
}
