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
use SolidWorx\Platform\PlatformBundle\Controller\Profile\ShowProfile;

/**
 * Adds the "Profile" entry to the user dropdown.
 *
 * It is registered above {@see UserMenu::PRIORITY_ACCOUNT} so it leads the dropdown, ahead of
 * the two-factor entry and anything an application appends.
 */
final class ProfileMenuBuilder
{
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
}
