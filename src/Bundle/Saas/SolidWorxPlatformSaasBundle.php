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

namespace SolidWorx\Platform\SaasBundle;

use Override;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfigSectionInterface;
use SolidWorx\Platform\SaasBundle\DependencyInjection\CompilerPass\ResolveTargetEntityPass;
use SolidWorx\Platform\SaasBundle\DependencyInjection\CompilerPass\WebhookCompilerPass;
use SolidWorx\Platform\SaasBundle\DependencyInjection\SolidWorxPlatformSaasExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SolidWorxPlatformSaasBundle extends Bundle implements PlatformConfigSectionInterface
{
    public const string NAMESPACE = __NAMESPACE__;

    /**
     * @var array<string, mixed>
     */
    private array $rawConfig = [];

    #[Override]
    public function getConfigSectionKey(): string
    {
        return 'saas';
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
        $container->addCompilerPass(new ResolveTargetEntityPass());
        $container->addCompilerPass(new WebhookCompilerPass());
    }

    #[Override]
    protected function createContainerExtension(): ?ExtensionInterface
    {
        return new SolidWorxPlatformSaasExtension($this->rawConfig);
    }
}
