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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Twig\Runtime;

use Knp\Menu\ItemInterface;
use Knp\Menu\MenuFactory;
use Knp\Menu\Provider\MenuProviderInterface;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Twig\Runtime\MenuRuntime;

#[CoversClass(MenuRuntime::class)]
final class MenuRuntimeTest extends TestCase
{
    public function testAKnownMenuExists(): void
    {
        self::assertTrue($this->runtime()->exists('sidebar'));
    }

    public function testAnUnknownMenuDoesNotExist(): void
    {
        self::assertFalse($this->runtime()->exists('navbar'));
    }

    private function runtime(): MenuRuntime
    {
        return new MenuRuntime(new class() implements MenuProviderInterface {
            #[Override]
            public function get(string $name, array $options = []): ItemInterface
            {
                return new MenuFactory()->createItem($name);
            }

            #[Override]
            public function has(string $name, array $options = []): bool
            {
                return $name === 'sidebar';
            }
        });
    }
}
