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

/**
 * The navigation down the side of the profile section.
 *
 * It is an ordinary KnpMenu, so an application adds its own account pages the same way it adds
 * sidebar or navbar entries — and they land in the profile navigation, rendered in the profile
 * layout, without a template being touched:
 *
 *     #[MenuBuilder(name: ProfileMenu::NAME)]
 *     public function build(ItemInterface $menu): void
 *     {
 *         $menu->addChild('Notifications', Options::create()->route('app_notifications')->icon('bell')->build());
 *         $menu->addChild('API keys', Options::create()->route('app_api_keys')->icon('key')->build());
 *     }
 *
 * A page added this way gets the navigation and the page header for free by extending the profile
 * layout:
 *
 *     {% extends '@SolidWorxPlatform/Profile/layout.html.twig' %}
 *
 *     {% block page_title %}{{ 'Notifications'|trans }}{% endblock %}
 *     {% block profile_content %}…{% endblock %}
 *
 * Builders run from the highest priority to the lowest and each one appends, so priority decides
 * where entries end up. The platform's own pages are above the default of `0`, which means
 * application entries land underneath them without having to pick a priority at all.
 *
 * This is a different menu from {@see UserMenu}: that one is the dropdown behind the avatar, which
 * holds a single **Profile** entry leading here rather than a copy of this list.
 */
final class ProfileMenu
{
    /**
     * The KnpMenu name the profile navigation is rendered from.
     */
    public const string NAME = 'profile_menu';

    /**
     * The priority the platform registers the profile and change-password entries with.
     *
     * Register above it to push an entry to the top of the navigation, below it (or leave the
     * priority at its default of `0`) to append underneath the platform entries.
     */
    public const int PRIORITY_ACCOUNT = 200;

    /**
     * The priority of the second-factor entry, which follows the account pages.
     *
     * It is separate from {@see self::PRIORITY_ACCOUNT} because the entry is registered by a
     * different builder — one that only exists when 2FA is enabled — and two builders sharing a
     * priority would order by registration rather than by intent.
     */
    public const int PRIORITY_SECURITY = 100;
}
