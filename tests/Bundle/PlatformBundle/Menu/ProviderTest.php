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

use Knp\Menu\ItemInterface;
use Knp\Menu\MenuFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Menu\Options;
use SolidWorx\Platform\PlatformBundle\Menu\Provider;
use SolidWorx\Platform\PlatformBundle\Menu\UserMenu;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use function array_keys;

#[CoversClass(Provider::class)]
#[CoversClass(UserMenu::class)]
#[CoversClass(Options::class)]
final class ProviderTest extends TestCase
{
    /**
     * The user menu relies on this: the platform registers its account entries above the default
     * priority so an application's builders — which have no reason to pick a priority — always
     * append underneath them.
     */
    public function testBuildersRunFromTheHighestPriorityDown(): void
    {
        $provider = $this->provider();

        $provider->addBuilder(
            static fn (ItemInterface $menu) => $menu->addChild('Application entry'),
            UserMenu::NAME,
            0,
            '',
        );

        $provider->addBuilder(
            static fn (ItemInterface $menu) => $menu->addChild('Platform entry'),
            UserMenu::NAME,
            UserMenu::PRIORITY_ACCOUNT,
            '',
        );

        self::assertSame(
            ['Platform entry', 'Application entry'],
            array_keys($provider->get(UserMenu::NAME)->getChildren()),
        );
    }

    public function testEqualPrioritiesKeepTheOrderTheyWereRegisteredIn(): void
    {
        $provider = $this->provider();

        foreach (['First', 'Second', 'Third'] as $label) {
            $provider->addBuilder(
                static fn (ItemInterface $menu) => $menu->addChild($label),
                UserMenu::NAME,
                0,
                '',
            );
        }

        self::assertSame(
            ['First', 'Second', 'Third'],
            array_keys($provider->get(UserMenu::NAME)->getChildren()),
        );
    }

    public function testTheMenuStillExistsWhenEveryBuilderIsFilteredOutByItsRole(): void
    {
        $provider = $this->provider(granted: false);

        $provider->addBuilder(
            static fn (ItemInterface $menu) => $menu->addChild('Admin entry'),
            UserMenu::NAME,
            0,
            'ROLE_ADMIN',
        );

        // `menu_exists()` has to stay true, otherwise the layout would fall back to a different
        // branch rather than simply rendering an empty menu.
        self::assertTrue($provider->has(UserMenu::NAME));
        self::assertSame([], $provider->get(UserMenu::NAME)->getChildren());
    }

    public function testItemsTheUserIsNotAuthorizedForAreRemoved(): void
    {
        $provider = $this->provider(granted: false);

        $provider->addBuilder(
            static function (ItemInterface $menu): void {
                $menu->addChild('Profile');
                $menu->addChild('Billing', Options::create()->role('ROLE_ADMIN')->build());
            },
            UserMenu::NAME,
            0,
            '',
        );

        self::assertSame(['Profile'], array_keys($provider->get(UserMenu::NAME)->getChildren()));
    }

    public function testTheSameMenuCanBeRenderedMoreThanOnce(): void
    {
        $provider = $this->provider();

        $provider->addBuilder(
            static fn (ItemInterface $menu) => $menu->addChild('Profile'),
            UserMenu::NAME,
            0,
            '',
        );

        // The `app` layout renders the user menu twice — once in the sidebar for small screens,
        // once in the top navbar — so consuming the queue must not empty it.
        self::assertSame(['Profile'], array_keys($provider->get(UserMenu::NAME)->getChildren()));
        self::assertSame(['Profile'], array_keys($provider->get(UserMenu::NAME)->getChildren()));
    }

    private function provider(bool $granted = true): Provider
    {
        $authorizationChecker = self::createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($granted);

        return new Provider(new MenuFactory(), $authorizationChecker);
    }
}
