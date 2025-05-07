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
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Override;
use SolidWorx\Platform\PlatformBundle\Doctrine\Type\UTCDateTimeType;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SolidWorxPlatformBundle extends AbstractBundle
{
    public const string NAMESPACE = __NAMESPACE__;

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $rootNode = $definition->rootNode();
        assert($rootNode instanceof ArrayNodeDefinition);

        //@formatter:off
        $rootNode
            ->children()
                ->arrayNode('doctrine')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('types')
                            ->fixXmlConfig('type')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enable_utc_date')
                                ->defaultTrue()
                                ->info('Enable UTC date type. This ensures that all dates are stored in UTC format in the database.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
        //@formatter:on
    }

    /**
     * @param array{doctrine: array{types: array{enable_utc_date: bool}}} $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('solidworx_platform.doctrine.types.enable_utc_date', $config['doctrine']['types']['enable_utc_date']);
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
            Type::overrideType(Types::DATETIMETZ_IMMUTABLE, UTCDateTimeType::class);
            Type::overrideType(Types::DATETIME_IMMUTABLE, UTCDateTimeType::class);
        }
    }

    #[Override]
    public function getPath(): string
    {
        return __DIR__;
    }
}
