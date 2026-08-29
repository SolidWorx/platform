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

namespace SolidWorx\Platform\Tests\Bundle\Ui\Layout;

use InvalidArgumentException;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Knp\Menu\Provider\MenuProviderInterface;
use Override;
use SolidWorx\Platform\PlatformBundle\Menu\UserMenu;
use function sprintf;

/**
 * Supplies the `sidebar`, `navbar` and `user_menu` menus the layouts look for, so the rendered
 * markup exercises the real KnpMenu → `@SolidWorxPlatform/Menu/*.html.twig` path. `reports` is
 * deliberately absent to cover the `menu_exists()` guard.
 */
final readonly class TestMenuProvider implements MenuProviderInterface
{
    private const array MENUS = ['sidebar', 'navbar', UserMenu::NAME];

    public function __construct(
        private FactoryInterface $factory,
    ) {
    }

    #[Override]
    public function get(string $name, array $options = []): ItemInterface
    {
        if (! $this->has($name, $options)) {
            throw new InvalidArgumentException(sprintf('The menu "%s" is not defined.', $name));
        }

        if ($name === UserMenu::NAME) {
            return $this->userMenu();
        }

        $root = $this->factory->createItem('root');

        $root->addChild('Dashboard', [
            'uri' => '/dashboard',
            'extras' => [
                'icon' => 'home',
            ],
        ]);

        // Deliberately without a uri, so it renders as a dropdown toggle rather than a plain link.
        $settings = $root->addChild('Settings', [
            'extras' => [
                'icon' => 'settings',
            ],
        ]);

        $settings->addChild('Team', [
            'uri' => '/settings/team',
        ]);

        return $root;
    }

    #[Override]
    public function has(string $name, array $options = []): bool
    {
        return in_array($name, self::MENUS, true);
    }

    /**
     * Covers every shape the dropdown template handles: a plain entry, an icon, a divider and a
     * grouping entry that has children but no URI.
     */
    private function userMenu(): ItemInterface
    {
        $root = $this->factory->createItem('root');

        $root->addChild('Profile', [
            'uri' => '/profile',
            'extras' => [
                'icon' => 'user',
            ],
        ]);

        $workspace = $root->addChild('Workspace', [
            'extras' => [
                'divider' => true,
            ],
        ]);

        $workspace->addChild('API keys', [
            'uri' => '/settings/api-keys',
        ]);

        return $root;
    }
}
