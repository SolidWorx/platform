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
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SolidWorx\Platform\PlatformBundle\Attributes\Menu\MenuBuilder;
use SolidWorx\Platform\PlatformBundle\Controller\Profile\ShowProfile;
use SolidWorx\Platform\PlatformBundle\Menu\Options;
use SolidWorx\Platform\PlatformBundle\Menu\ProfileMenuBuilder;
use SolidWorx\Platform\PlatformBundle\Menu\TwoFactorMenuBuilder;
use SolidWorx\Platform\PlatformBundle\Menu\UserMenu;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(ProfileMenuBuilder::class)]
#[UsesClass(UserMenu::class)]
#[UsesClass(Options::class)]
final class ProfileMenuBuilderTest extends TestCase
{
    public function testItLinksToTheProfilePage(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with(ShowProfile::ROUTE_NAME, [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn(ShowProfile::PATH);

        $factory = new MenuFactory();
        $factory->addExtension(new RoutingExtension($urlGenerator));

        $menu = $factory->createItem('root');

        new ProfileMenuBuilder()->build($menu);

        $item = $menu->getChild('Profile');

        self::assertNotNull($item);
        self::assertSame(ShowProfile::PATH, $item->getUri());
        self::assertSame('user', $item->getExtra('icon'));
    }

    /**
     * Builders run highest priority first and each one appends, so "Profile" only leads the
     * dropdown as long as it outranks both the platform's other account entries and an
     * application's, which default to `0`.
     */
    public function testItLeadsTheUserDropdown(): void
    {
        $attributes = new ReflectionMethod(ProfileMenuBuilder::class, 'build')->getAttributes(MenuBuilder::class);

        self::assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();

        self::assertSame(UserMenu::NAME, $attribute->name);
        self::assertGreaterThan(UserMenu::PRIORITY_ACCOUNT, $attribute->priority);
    }

    /**
     * The two-factor entry belongs under the profile entry, not above it.
     */
    public function testItOutranksTheTwoFactorEntry(): void
    {
        self::assertGreaterThan(
            self::priorityOf(TwoFactorMenuBuilder::class),
            self::priorityOf(ProfileMenuBuilder::class),
        );
    }

    /**
     * @param class-string $builder
     */
    private static function priorityOf(string $builder): int
    {
        $attributes = new ReflectionMethod($builder, 'build')->getAttributes(MenuBuilder::class);

        return $attributes[0]->newInstance()->priority;
    }
}
