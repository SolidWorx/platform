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

namespace SolidWorx\Platform\UiBundle\DependencyInjection;

use Override;
use SolidWorx\Platform\UiBundle\Config\UiConfiguration;
use SolidWorx\Platform\UiBundle\Twig\UiExtension;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use function dirname;

final class SolidWorxPlatformUiExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @var array{
     *   icon_pack: string,
     *   templates: array{base: string, login: string}
     * }|null
     */
    private ?array $config = null;

    /**
     * @param array<string, mixed> $rawSection The raw (unvalidated) `platform.ui:` config section.
     */
    public function __construct(
        private readonly array $rawSection
    ) {
    }

    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(dirname(__DIR__) . '/Resources/config'));
        $loader->import('services.php');

        $config = $this->getConfig();

        $container
            ->getDefinition(UiExtension::class)
            ->setArgument(0, $config['templates']['base'])
        ;

        $container->setParameter('solidworx_platform_ui.template.login', $config['templates']['login']);
    }

    #[Override]
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('twig_component')) {
            $container->prependExtensionConfig('twig_component', [
                'defaults' => [
                    'UiBundle\Twig\Component\\' => [
                        'template_directory' => '@Ui/components/Ui/',
                        'name_prefix' => 'Ui',
                    ],
                ],
            ]);
        }

        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', [
                'paths' => [
                    dirname(__DIR__) . '/templates/' => 'Ui',
                ],
            ]);
        }
    }

    /**
     * @return array{
     *   icon_pack: string,
     *   templates: array{base: string, login: string}
     * }
     */
    private function getConfig(): array
    {
        if ($this->config === null) {
            $this->config = $this->processRawSection();
        }

        return $this->config;
    }

    /**
     * @return array{
     *   icon_pack: string,
     *   templates: array{base: string, login: string}
     * }
     */
    private function processRawSection(): array
    {
        $treeBuilder = (new UiConfiguration())->getTreeBuilder();

        $processor = new Processor();

        /** @var array{icon_pack: string, templates: array{base: string, login: string}} */
        return $processor->process($treeBuilder->buildTree(), [$this->rawSection]);
    }
}
