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

namespace SolidWorx\Platform\PlatformBundle;

use Doctrine\DBAL\Exception;
use LogicException;
use Override;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfigSectionInterface;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\CompilerPass\AuthenticationCompilerPass;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\CompilerPass\ClearPlatformConfigStatePass;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\CompilerPass\MenuCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use function sprintf;

final class SolidWorxPlatformBundle extends Bundle implements PlatformConfigSectionInterface
{
    public const string NAMESPACE = __NAMESPACE__;

    /**
     * @var array<string, mixed>
     */
    private array $rawConfig = [];

    #[Override]
    public function getConfigSectionKey(): string
    {
        return '';
    }

    #[Override]
    public function setPlatformRawConfig(array $rawConfig): void
    {
        $this->rawConfig = $rawConfig;
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new MenuCompilerPass());
        $container->addCompilerPass(new AuthenticationCompilerPass());
        $container->addCompilerPass(new ClearPlatformConfigStatePass(), PassConfig::TYPE_AFTER_REMOVING);
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function boot(): void
    {
        if (! $this->container instanceof ContainerInterface) {
            return;
        }

        if (! $this->container->hasParameter('solidworx_platform.doctrine.types.enable_utc_date')) {
            return;
        }

        $parameter = $this->container->getParameter('solidworx_platform.doctrine.types.enable_utc_date');
        if ($parameter === true) {
            /*Type::overrideType(Types::DATETIMETZ_IMMUTABLE, UTCDateTimeType::class);
            Type::overrideType(Types::DATETIME_IMMUTABLE, UTCDateTimeType::class);*/
        }
    }

    #[Override]
    public function getPath(): string
    {
        return __DIR__;
    }

    #[Override]
    protected function createContainerExtension(): ExtensionInterface
    {
        $extension = new ($this->getContainerExtensionClass())($this->rawConfig);

        if (! $extension instanceof ExtensionInterface) {
            throw new LogicException(sprintf('Extension "%s" must implement "%s".', $extension::class, ExtensionInterface::class));
        }

        return $extension;
    }
}
