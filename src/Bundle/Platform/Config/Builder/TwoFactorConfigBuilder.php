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

namespace SolidWorx\Platform\PlatformBundle\Config\Builder;

final class TwoFactorConfigBuilder
{
    private bool $enabled = false;

    private ?string $baseTemplate = null;

    private function __construct(
        private readonly SecurityConfigBuilder $parent
    ) {
    }

    public static function create(SecurityConfigBuilder $parent): self
    {
        return new self($parent);
    }

    public function enabled(bool $enabled = true): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function baseTemplate(string $template): self
    {
        $this->baseTemplate = $template;
        return $this;
    }

    public function end(): SecurityConfigBuilder
    {
        return $this->parent;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $config = [
            'enabled' => $this->enabled,
        ];

        if ($this->baseTemplate !== null) {
            $config['base_template'] = $this->baseTemplate;
        }

        return $config;
    }
}
