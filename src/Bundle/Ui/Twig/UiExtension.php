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

namespace SolidWorx\Platform\UiBundle\Twig;

use Override;
use SolidWorx\Platform\UiBundle\Config\UiConfiguration;
use SolidWorx\Platform\UiBundle\Twig\Runtime\LayoutRuntime;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * Exposes the configured layouts and the layout option helpers to Twig.
 *
 * Templates extend `ui_layout_app` rather than hard-coding `@Ui/Layout/app.html.twig`, which lets an
 * application swap any layout for its own through `platform.ui.templates.layouts`.
 *
 * @phpstan-import-type UiLayoutTemplates from UiConfiguration
 */
final class UiExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @param UiLayoutTemplates $layoutTemplates
     */
    public function __construct(
        private readonly string $baseTemplate,
        private readonly array $layoutTemplates,
        #[Autowire(param: 'solidworx_platform.app.name')]
        private readonly string $appName,
    ) {
    }

    #[Override]
    public function getGlobals(): array
    {
        $globals = [
            'ui_base_template' => $this->baseTemplate,
            'ui_app_name' => $this->appName,
        ];

        foreach ($this->layoutTemplates as $name => $template) {
            $globals['ui_layout_' . $name] = $template;
        }

        return $globals;
    }

    /**
     * @return TwigFunction[]
     */
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ui_layout', [LayoutRuntime::class, 'layout']),
            new TwigFunction('ui_layout_resolve', [LayoutRuntime::class, 'resolve']),
        ];
    }
}
