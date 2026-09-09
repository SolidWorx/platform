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

namespace SolidWorx\Platform\DataGridBundle\DependencyInjection;

use Override;
use SolidWorx\Platform\DataGridBundle\Config\DataGridConfiguration;
use SolidWorx\Platform\DataGridBundle\Grid\AbstractDataGrid;
use SolidWorx\Platform\DataGridBundle\Grid\DataGridDefaults;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Webmozart\Assert\Assert;
use function dirname;

/**
 * @phpstan-import-type DataGridConfig from DataGridConfiguration
 */
final class SolidWorxPlatformDataGridExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @var DataGridConfig|null
     */
    private ?array $config = null;

    /**
     * @param array<string, mixed> $rawSection The raw (unvalidated) `datagrid:` config section.
     */
    public function __construct(
        private readonly array $rawSection = []
    ) {
        Assert::allString(array_keys($rawSection));
    }

    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->getConfig();

        $loader = new PhpFileLoader($container, new FileLocator(dirname(__DIR__) . '/Resources/config'));
        $loader->import('services.php');

        $container->setParameter('solidworx_platform_datagrid.page_length', $config['page_length']);
        $container->setParameter('solidworx_platform_datagrid.length_menu', $config['length_menu']);
        $container->setParameter('solidworx_platform_datagrid.responsive', $config['responsive']);
        $container->setParameter('solidworx_platform_datagrid.column_control', $config['column_control']);
        $container->setParameter('solidworx_platform_datagrid.table_class', $config['table_class']);
        $container->setParameter('solidworx_platform_datagrid.security.ajax_access', $config['security']['ajax_access']);
        $container->setParameter('solidworx_platform_datagrid.export.enabled', $config['export']['enabled']);
        $container->setParameter('solidworx_platform_datagrid.export.formats', $config['export']['formats']);
        $container->setParameter('solidworx_platform_datagrid.edit_modal.enabled', $config['edit_modal']['enabled']);

        // Setter injection rather than constructor arguments, so a grid subclass
        // keeps a zero-argument constructor. Upstream injects its own
        // DataTableInfrastructure the same way.
        $container->registerForAutoconfiguration(AbstractDataGrid::class)
            ->addMethodCall('setDataGridDefaults', [new Reference(DataGridDefaults::class)]);
    }

    #[Override]
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', [
                'paths' => [
                    dirname(__DIR__) . '/templates' => 'DataGrid',
                ],
            ]);
        }

        if (! $container->hasExtension('data_tables')) {
            return;
        }

        $config = $this->getConfig();

        $container->prependExtensionConfig('data_tables', [
            'options' => [
                'pageLength' => $config['page_length'],
                'lengthMenu' => $config['length_menu'],
            ],
            'template_parameters' => [
                'class' => $config['table_class'],
            ],
            // Upstream defaults these to its own DataTables-styled modal; point
            // them at the Bootstrap 5 variants it also ships, so the modal
            // matches Tabler.
            'edit_modal' => [
                'template' => '@DataTables/modal/bs5/edit_modal.html.twig',
                'body_template' => '@DataTables/modal/bs5/_form_body.html.twig',
            ],
        ]);
    }

    /**
     * @return DataGridConfig
     */
    private function getConfig(): array
    {
        /** @var DataGridConfig */
        return $this->config ??= new Processor()->process(
            new DataGridConfiguration()->getTreeBuilder()->buildTree(),
            [$this->rawSection],
        );
    }
}
