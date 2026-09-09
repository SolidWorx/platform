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

namespace SolidWorx\Platform\DataGridBundle;

use Override;
use Pentiminax\UX\DataTables\DataTablesBundle;
use SolidWorx\Platform\DataGridBundle\DependencyInjection\CompilerPass\DataGridRegistryPass;
use SolidWorx\Platform\DataGridBundle\DependencyInjection\SolidWorxPlatformDataGridExtension;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfigSectionInterface;
use SolidWorx\Platform\PlatformBundle\SolidWorxPlatformBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;

#[RequiredBundle(SolidWorxPlatformBundle::class)]
#[RequiredBundle(DataTablesBundle::class)]
final class SolidWorxPlatformDataGridBundle extends Bundle implements PlatformConfigSectionInterface
{
    public const string NAMESPACE = __NAMESPACE__;

    /**
     * @var array<string, mixed>
     */
    private array $rawConfig = [];

    #[Override]
    public function getConfigSectionKey(): string
    {
        return 'datagrid';
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
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new DataGridRegistryPass());
    }

    #[Override]
    protected function createContainerExtension(): ExtensionInterface
    {
        return new SolidWorxPlatformDataGridExtension($this->rawConfig);
    }
}
