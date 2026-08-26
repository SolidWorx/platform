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

namespace SolidWorx\Platform\PlatformBundle\Twig\Runtime;

use Knp\Menu\Provider\MenuProviderInterface;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class MenuRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private MenuProviderInterface $menuProvider,
    ) {
    }

    /**
     * Layouts render optional menus (`sidebar`, `navbar`, …) that an application may never register,
     * so they need to ask before rendering rather than let the provider throw.
     *
     * @param array<string, mixed> $options
     */
    public function exists(string $name, array $options = []): bool
    {
        return $this->menuProvider->has($name, $options);
    }
}
