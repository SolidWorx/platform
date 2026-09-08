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
 * The dropdown behind the avatar in the top-right of the navbar.
 *
 * It is an ordinary KnpMenu, so an application adds its own entries the same way it adds
 * sidebar or navbar entries:
 *
 *     #[MenuBuilder(name: UserMenu::NAME)]
 *     public function build(ItemInterface $menu): void
 *     {
 *         $menu->addChild('Billing', Options::create()->route('app_billing')->icon('credit-card')->build());
 *     }
 *
 * Builders run from the highest priority to the lowest, and each one appends to the menu, so
 * priority decides where entries end up in the dropdown. The platform's own entries — the profile
 * page at {@see self::PRIORITY_PROFILE}, two-factor authentication at
 * {@see self::PRIORITY_ACCOUNT} — are both above the default of `0`, so application entries land
 * underneath them without having to pick a priority at all.
 *
 * The logout link is not part of this menu: it is a CSRF-protected form rather than a link, and
 * the UI bundle always renders it last, under a divider.
 */
final class UserMenu
{
    /**
     * The KnpMenu name the user dropdown is rendered from.
     */
    public const string NAME = 'user_menu';

    /**
     * The priority the platform registers its own account entries with.
     *
     * Register above it to push an entry to the top of the dropdown, below it (or leave the
     * priority at its default of `0`) to append underneath the platform entries.
     */
    public const int PRIORITY_ACCOUNT = 100;

    /**
     * The priority of the "Profile" entry, which sits above the rest of the account entries.
     *
     * The profile page is the way in to everything else about the account — the details, the
     * password, the second factors — so it leads the dropdown.
     */
    public const int PRIORITY_PROFILE = 200;
}
