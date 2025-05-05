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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class UiBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('twig_component')) {
            $builder->prependExtensionConfig('twig_component', [
                'defaults' => [
                    'UiBundle\Twig\Component\\' => [
                        'template_directory' => '@Ui/components/',
                        'name_prefix' => 'Ui',
                    ],
                ],
            ]);
        }

        if ($builder->hasExtension('framework')) {
            $builder->prependExtensionConfig('framework', [
                'asset_mapper' => [
                    'paths' => [
                        __DIR__ . '/../../../assets/ui/dist' => '@solidworx/ui-component',
                    ],
                ],
            ]);
        }

        if ($builder->hasExtension('twig')) {
            $builder->prependExtensionConfig('twig', [
                'paths' => [
                    __DIR__ . '/templates/' => null,
                ],
            ]);
        }
    }
}
