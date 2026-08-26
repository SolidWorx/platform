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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Exposes the configured layouts and their defaults to Twig, so templates extend
 * `ui_layout_app` rather than hard-coding `@Ui/Layout/app.html.twig` — which lets an application
 * swap any layout for its own through `platform.ui.templates.layouts`.
 *
 * @phpstan-import-type UiLayoutOptions from UiConfiguration
 * @phpstan-import-type UiLayoutTemplates from UiConfiguration
 */
final class UiExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @param UiLayoutTemplates $layoutTemplates
     * @param UiLayoutOptions   $layoutDefaults
     */
    public function __construct(
        private readonly string $baseTemplate,
        private readonly array $layoutTemplates,
        private readonly array $layoutDefaults,
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
            'ui_layout_defaults' => $this->layoutDefaults,
        ];

        foreach ($this->layoutTemplates as $name => $template) {
            $globals['ui_layout_' . $name] = $template;
        }

        return $globals;
    }
}
