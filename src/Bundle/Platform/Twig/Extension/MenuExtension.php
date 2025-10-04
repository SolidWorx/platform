<?php

namespace SolidWorx\Platform\PlatformBundle\Twig\Extension;

use Knp\Menu\Twig\MenuRuntimeExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MenuExtension extends AbstractExtension
{
    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_menu', [MenuRuntimeExtension::class, 'render'], [
                'is_safe' => ['html'],
            ]),
        ];
    }
}
