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

namespace SolidWorx\Platform\PlatformBundle\Menu;

use Knp\Menu\ItemInterface;
use SolidWorx\Platform\PlatformBundle\Attributes\Menu\MenuBuilder;
use SolidWorx\Platform\PlatformBundle\Controller\Profile\ChangePassword;
use SolidWorx\Platform\PlatformBundle\Controller\Profile\ShowProfile;

/**
 * The platform's own entries in the two menus the profile section uses.
 *
 * The user dropdown gets a single **Profile** entry — the way in to the section — while the
 * section's own navigation lists the pages inside it. The dropdown deliberately does not mirror
 * that list: it is the shortcut, not a second copy of the navigation.
 */
final class ProfileMenuBuilder
{
    /**
     * The **Profile** entry in the user dropdown.
     *
     * It is registered above {@see UserMenu::PRIORITY_ACCOUNT} so it leads the dropdown, ahead of
     * anything an application appends.
     */
    #[MenuBuilder(name: UserMenu::NAME, priority: UserMenu::PRIORITY_PROFILE)]
    public function build(ItemInterface $menu): void
    {
        $menu->addChild(
            'Profile',
            Options::create()
                ->route(ShowProfile::ROUTE_NAME)
                ->icon('user')
                ->build(),
        );
    }

    /**
     * The pages the platform itself puts in the profile navigation.
     *
     * The two-factor entry is not here: it is registered by {@see TwoFactorMenuBuilder}, whose
     * service only exists when 2FA is enabled.
     */
    #[MenuBuilder(name: ProfileMenu::NAME, priority: ProfileMenu::PRIORITY_ACCOUNT)]
    public function buildProfileMenu(ItemInterface $menu): void
    {
        $menu->addChild(
            'Profile',
            Options::create()
                ->route(ShowProfile::ROUTE_NAME)
                ->icon('user')
                ->build(),
        );

        $menu->addChild(
            'Change password',
            Options::create()
                ->route(ChangePassword::ROUTE_NAME)
                ->icon('lock')
                ->build(),
        );
    }
}
