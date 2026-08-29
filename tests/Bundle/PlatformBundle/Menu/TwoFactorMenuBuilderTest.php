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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Menu;

use Knp\Menu\Integration\Symfony\RoutingExtension;
use Knp\Menu\MenuFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SolidWorx\Platform\PlatformBundle\Attributes\Menu\MenuBuilder;
use SolidWorx\Platform\PlatformBundle\Controller\Security\TwoFactorConfiguration;
use SolidWorx\Platform\PlatformBundle\Menu\Options;
use SolidWorx\Platform\PlatformBundle\Menu\TwoFactorMenuBuilder;
use SolidWorx\Platform\PlatformBundle\Menu\UserMenu;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(TwoFactorMenuBuilder::class)]
#[CoversClass(UserMenu::class)]
#[CoversClass(Options::class)]
final class TwoFactorMenuBuilderTest extends TestCase
{
    public function testItLinksToTheTwoFactorConfigurationPage(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with(TwoFactorConfiguration::ROUTE_NAME, [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn(TwoFactorConfiguration::PATH);

        $factory = new MenuFactory();
        $factory->addExtension(new RoutingExtension($urlGenerator));

        $menu = $factory->createItem('root');

        new TwoFactorMenuBuilder()->build($menu);

        $item = $menu->getChild('Two-factor authentication');

        self::assertNotNull($item);
        self::assertSame(TwoFactorConfiguration::PATH, $item->getUri());
        self::assertSame('shield-lock', $item->getExtra('icon'));
    }

    /**
     * Builders run highest priority first, and each one appends — so registering above the
     * default priority of `0` is what keeps the platform's own entries above an application's.
     */
    public function testItIsRegisteredOnTheUserMenuAboveApplicationEntries(): void
    {
        $attributes = new ReflectionMethod(TwoFactorMenuBuilder::class, 'build')->getAttributes(MenuBuilder::class);

        self::assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();

        self::assertSame(UserMenu::NAME, $attribute->name);
        self::assertGreaterThan(0, $attribute->priority);
    }
}
