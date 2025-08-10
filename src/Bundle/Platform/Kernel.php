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

use const GLOB_BRACE;
use const PATHINFO_EXTENSION;
use Override;
use RuntimeException;
use Scheb\TwoFactorBundle\SchebTwoFactorBundle;
use SolidWorx\Platform\PlatformBundle\Config\Configuration;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfig;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\PlatformExtension;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Yaml\Yaml;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;
use function glob;
use function implode;
use function pathinfo;
use function sprintf;
use function var_export;

abstract class Kernel extends BaseKernel
{
    use MicroKernelTrait {
        registerBundles as registerBundlesTrait;
        configureContainer as private configureContainerTrait;
        configureRoutes as private configureRoutesTrait;
    }

    private const string PLATFORM_CONFIG_FILE = 'platform.{xml,yml,yaml}';

    /**
     * @var array|mixed
     */
    private PlatformConfig $platformConfig;

    #[Override]
    public function boot(): void
    {
        if ($this->booted) {
            parent::boot();
            return;
        }

        $this->processPlatformConfig();

        parent::boot();
    }

    #[Override]
    public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): Response
    {
        $this->processPlatformConfig();

        return parent::handle($request, $type, $catch);
    }

    #[Override]
    public function registerBundles(): iterable
    {
        $bundles = yield from $this->registerBundlesTrait();

        if ($this->platformConfig->get('security.2fa.enabled') === true) {
            yield new SchebTwoFactorBundle();
        }

        yield new TwigExtraBundle();

        return $bundles;
    }

    #[Override]
    protected function initializeBundles(): void
    {
        // init bundles
        $this->bundles = [];
        foreach ($this->registerBundles() as $bundle) {
            $name = $bundle->getName();
            if (isset($this->bundles[$name])) {
                // Bundle is already registered, let's skip it
                continue;
            }

            $this->bundles[$name] = $bundle;
        }
    }

    protected function getPlatformConfigFile(): string
    {
        return $this->getProjectDir() . '/' . self::PLATFORM_CONFIG_FILE;
    }

    protected function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        $builder->registerExtension(new PlatformExtension($this->platformConfig));
        $this->configureContainerTrait($container, $loader, $builder);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $this->configureRoutesTrait($routes);
    }

    private function processPlatformConfig(): void
    {
        if (isset($this->platformConfig)) {
            return;
        }

        $platformConfigFile = $this->getPlatformConfigFile();

        $configCache = new ConfigCache($this->getCacheDir() . '/Platform/config.php', $this->debug);

        if ($configCache->isFresh()) {
            $this->platformConfig = new PlatformConfig(require $configCache->getPath());
            return;
        }

        $configFiles = glob($platformConfigFile, is_define('GLOB_BRACE') ? GLOB_BRACE : 0);

        if ($configFiles === []) {
            return;
        }

        if (count($configFiles) > 1) {
            throw new RuntimeException(sprintf(
                'Multiple platform configuration files found: %s. Please ensure there is only one configuration file.',
                implode(', ', $configFiles)
            ));
        }

        $ext = pathinfo($configFiles[0], PATHINFO_EXTENSION);

        $parsedConfig = match ($ext) {
            'xml' => XmlUtils::loadFile($configFiles[0]),
            'yaml', 'yml' => Yaml::parseFile($configFiles[0]),
            default => throw new RuntimeException(sprintf('Unsupported configuration file format: %s', $ext)),
        };

        $processor = new Processor();
        $this->platformConfig = new PlatformConfig($processor->processConfiguration(new Configuration(), $parsedConfig));

        $configCache->write(
            '<?php return ' . var_export($this->platformConfig->toArray(), true) . ';',
        );
    }
}
