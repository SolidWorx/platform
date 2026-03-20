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

namespace SolidWorx\Platform\UiBundle\Config\Builder;

/**
 * PHP fluent builder for the `platform.ui:` configuration section.
 *
 * Usage:
 *
 *     use SolidWorx\Platform\UiBundle\Config\Builder\UiConfigBuilder;
 *     use SolidWorx\Platform\PlatformBundle\Config\Builder\PlatformConfigBuilder;
 *
 *     return PlatformConfigBuilder::create()
 *         ->withUiConfig(
 *             UiConfigBuilder::create()
 *                 ->iconPack('tabler')
 *                 ->baseTemplate('@App/layout/base.html.twig')
 *                 ->build()
 *         )
 *         ->build();
 */
final class UiConfigBuilder
{
    private string $iconPack = 'tabler';

    private string $baseTemplate = '@Ui/Layout/base.html.twig';

    private string $loginTemplate = '@Ui/Security/login.html.twig';

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function iconPack(string $pack): self
    {
        $this->iconPack = $pack;
        return $this;
    }

    public function baseTemplate(string $template): self
    {
        $this->baseTemplate = $template;
        return $this;
    }

    public function loginTemplate(string $template): self
    {
        $this->loginTemplate = $template;
        return $this;
    }

    /**
     * Returns the raw ui config array — no validation at this stage.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return [
            'icon_pack' => $this->iconPack,
            'templates' => [
                'base' => $this->baseTemplate,
                'login' => $this->loginTemplate,
            ],
        ];
    }
}
