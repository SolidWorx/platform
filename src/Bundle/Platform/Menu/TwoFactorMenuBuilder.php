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
use SolidWorx\Platform\PlatformBundle\Controller\Security\TwoFactorConfiguration;

/**
 * Adds the two-factor authentication entry to the profile navigation.
 *
 * It belongs in the profile section rather than the user dropdown: turning a second factor on is
 * something a user does once, from the same place they change their password, not a destination
 * worth a permanent shortcut behind the avatar. The dropdown keeps a single **Profile** entry,
 * which leads here.
 *
 * The service is removed from the container when `platform.security.two_factor.enabled` is
 * false, so the entry — and the route it points at — can never be rendered for an application
 * that has not enabled 2FA.
 */
final class TwoFactorMenuBuilder
{
    #[MenuBuilder(name: ProfileMenu::NAME, priority: ProfileMenu::PRIORITY_SECURITY)]
    public function build(ItemInterface $menu): void
    {
        $menu->addChild(
            'Two-factor authentication',
            Options::create()
                ->route(TwoFactorConfiguration::ROUTE_NAME)
                ->icon('shield-lock')
                ->build(),
        );
    }
}
