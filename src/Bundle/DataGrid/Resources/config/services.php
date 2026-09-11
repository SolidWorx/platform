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

use SolidWorx\Platform\DataGridBundle\Grid\DataGridRegistry;
use SolidWorx\Platform\DataGridBundle\Grid\DataGridRenderer;
use SolidWorx\Platform\DataGridBundle\SolidWorxPlatformDataGridBundle;
use SolidWorx\Platform\DataGridBundle\Twig\DataGridTwigExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services
        ->defaults()
        ->autoconfigure()
        ->autowire()
        ->private()
    ;

    $services
        ->load(SolidWorxPlatformDataGridBundle::NAMESPACE . '\\', dirname(__DIR__, 2))
        ->exclude(dirname(__DIR__, 2) . '/{Config,DependencyInjection,Resources,templates}');

    // The locator argument is supplied by DataGridRegistryPass at compile time,
    // so there is nothing for the autowirer to resolve here.
    $services->set(DataGridRegistry::class)
        ->autowire(false);

    // Upstream registers DataTablesExtension only under the service id
    // "datatables.twig_extension", with no class alias — so autowiring
    // DataGridRenderer's DataTablesExtension argument below cannot resolve it.
    // Decorating gives DataGridRenderer that real, inner extension by id,
    // while also swapping render_datatable() for one that renders through us.
    $services->set(DataGridTwigExtension::class)
        ->decorate('datatables.twig_extension')
        ->args([service(DataGridRenderer::class)]);

    $services->set(DataGridRenderer::class)
        ->args([service(DataGridTwigExtension::class . '.inner')]);
};
