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

namespace SolidWorx\Platform\UiBundle;

use Override;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfigSectionInterface;
use SolidWorx\Platform\UiBundle\DependencyInjection\SolidWorxPlatformUiExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SolidWorxPlatformUiBundle extends Bundle implements PlatformConfigSectionInterface
{
    public const string NAMESPACE = __NAMESPACE__;

    /**
     * @var array<string, mixed>
     */
    private array $rawConfig = [];

    #[Override]
    public function getConfigSectionKey(): string
    {
        return 'ui';
    }

    #[Override]
    public function setPlatformRawConfig(array $rawConfig): void
    {
        $this->rawConfig = $rawConfig;
    }

    #[Override]
    public function getPath(): string
    {
        return __DIR__;
    }

    #[Override]
    protected function createContainerExtension(): ?ExtensionInterface
    {
        return new SolidWorxPlatformUiExtension($this->rawConfig);
    }
}
