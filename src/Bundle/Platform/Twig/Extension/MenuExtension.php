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

namespace SolidWorx\Platform\PlatformBundle\Twig\Extension;

use Knp\Menu\Twig\MenuRuntimeExtension;
use Override;
use SolidWorx\Platform\PlatformBundle\Twig\Runtime\MenuRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MenuExtension extends AbstractExtension
{
    /**
     * @return TwigFunction[]
     */
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_menu', [MenuRuntimeExtension::class, 'render'], [
                'is_safe' => ['html'],
            ]),
            new TwigFunction('menu_exists', [MenuRuntime::class, 'exists']),
        ];
    }
}
