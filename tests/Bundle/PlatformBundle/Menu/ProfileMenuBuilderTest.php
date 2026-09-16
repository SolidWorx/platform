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
use Knp\Menu\ItemInterface;
use Knp\Menu\MenuFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SolidWorx\Platform\PlatformBundle\Attributes\Menu\MenuBuilder;
use SolidWorx\Platform\PlatformBundle\Controller\Profile\ChangePassword;
use SolidWorx\Platform\PlatformBundle\Controller\Profile\ShowProfile;
use SolidWorx\Platform\PlatformBundle\Menu\Options;
use SolidWorx\Platform\PlatformBundle\Menu\ProfileMenu;
use SolidWorx\Platform\PlatformBundle\Menu\ProfileMenuBuilder;
use SolidWorx\Platform\PlatformBundle\Menu\TwoFactorMenuBuilder;
use SolidWorx\Platform\PlatformBundle\Menu\UserMenu;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use function array_keys;

#[CoversClass(ProfileMenuBuilder::class)]
#[UsesClass(UserMenu::class)]
#[UsesClass(ProfileMenu::class)]
#[UsesClass(Options::class)]
final class ProfileMenuBuilderTest extends TestCase
{
    public function testItLinksToTheProfilePageFromTheUserDropdown(): void
    {
        $menu = self::menu([
            ShowProfile::ROUTE_NAME => ShowProfile::PATH,
        ]);

        new ProfileMenuBuilder()->build($menu);

        $item = $menu->getChild('Profile');

        self::assertNotNull($item);
        self::assertSame(ShowProfile::PATH, $item->getUri());
        self::assertSame('user', $item->getExtra('icon'));
    }

    /**
     * The dropdown holds the way in to the profile section, not a copy of its navigation — one
     * entry, so the account pages are found in one place rather than two.
     */
    public function testTheUserDropdownCarriesOnlyTheProfileEntry(): void
    {
        $menu = self::menu([
            ShowProfile::ROUTE_NAME => ShowProfile::PATH,
        ]);

        new ProfileMenuBuilder()->build($menu);

        self::assertSame(['Profile'], array_keys($menu->getChildren()));
    }

    /**
     * Builders run highest priority first and each one appends, so "Profile" only leads the
     * dropdown as long as it outranks an application's entries, which default to `0`.
     */
    public function testItLeadsTheUserDropdown(): void
    {
        $attribute = self::attributeOn(ProfileMenuBuilder::class, 'build');

        self::assertSame(UserMenu::NAME, $attribute->name);
        self::assertGreaterThan(UserMenu::PRIORITY_ACCOUNT, $attribute->priority);
    }

    public function testTheProfileNavigationListsTheAccountPagesInOrder(): void
    {
        $menu = self::menu([
            ShowProfile::ROUTE_NAME => ShowProfile::PATH,
            ChangePassword::ROUTE_NAME => ChangePassword::PATH,
        ]);

        new ProfileMenuBuilder()->buildProfileMenu($menu);

        self::assertSame(['Profile', 'Change password'], array_keys($menu->getChildren()));

        $profile = $menu->getChild('Profile');
        $password = $menu->getChild('Change password');

        self::assertNotNull($profile);
        self::assertNotNull($password);

        self::assertSame(ShowProfile::PATH, $profile->getUri());
        self::assertSame(ChangePassword::PATH, $password->getUri());
        self::assertSame('lock', $password->getExtra('icon'));
    }

    public function testTheProfileNavigationEntriesLeadTheMenu(): void
    {
        $attribute = self::attributeOn(ProfileMenuBuilder::class, 'buildProfileMenu');

        self::assertSame(ProfileMenu::NAME, $attribute->name);
        self::assertGreaterThan(0, $attribute->priority, 'Application entries default to 0 and must land underneath.');
    }

    /**
     * The second factor belongs in the profile navigation, under the account pages — and nowhere
     * near the user dropdown, which is not where account settings live any more.
     */
    public function testTheTwoFactorEntryFollowsTheAccountPagesInTheProfileNavigation(): void
    {
        $attribute = self::attributeOn(TwoFactorMenuBuilder::class, 'build');

        self::assertSame(ProfileMenu::NAME, $attribute->name);
        self::assertLessThan(
            self::attributeOn(ProfileMenuBuilder::class, 'buildProfileMenu')->priority,
            $attribute->priority,
        );
        self::assertGreaterThan(0, $attribute->priority, 'Application entries default to 0 and must land underneath.');
    }

    /**
     * @param array<string, string> $routes
     */
    private static function menu(array $routes): ItemInterface
    {
        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturnCallback(static fn (string $route): string => $routes[$route] ?? '/');

        $factory = new MenuFactory();
        $factory->addExtension(new RoutingExtension($urlGenerator));

        return $factory->createItem('root');
    }

    /**
     * @param class-string $builder
     */
    private static function attributeOn(string $builder, string $method): MenuBuilder
    {
        $attributes = new ReflectionMethod($builder, $method)->getAttributes(MenuBuilder::class);

        self::assertCount(1, $attributes);

        return $attributes[0]->newInstance();
    }
}
