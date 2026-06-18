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
use Knp\Bundle\MenuBundle\KnpMenuBundle;
use Override;
use RuntimeException;
use Scheb\TwoFactorBundle\SchebTwoFactorBundle;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfigSectionInterface;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfigState;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Yaml\Yaml;
use Symfony\UX\Icons\UXIconsBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;
use function defined;
use function file_exists;
use function glob;
use function implode;
use function is_array;
use function is_file;
use function is_string;
use function pathinfo;
use function sprintf;

abstract class Kernel extends BaseKernel
{
    use MicroKernelTrait {
        registerBundles as registerBundlesTrait;
        configureRoutes as private configureRoutesTrait;
    }

    private const string DEFAULT_CONFIG_GLOB = 'platform.{yml,yaml,json,php}';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $rawConfig = null;

    #[Override]
    public function boot(): void
    {
        if (! $this->booted) {
            $this->processPlatformConfig();
        }

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
        foreach ($this->registerBundlesTrait() as $bundle) {
            if ($bundle instanceof BundleInterface) {
                yield $bundle;
            }
        }

        if ($this->isTwoFactorEnabled()) {
            yield new SchebTwoFactorBundle();
        }

        yield new SolidWorxPlatformBundle();

        yield new TwigExtraBundle();
        yield new KnpMenuBundle();
        yield new UXIconsBundle();
        // yield new SymfonyCastsResetPasswordBundle();
    }

    #[Override]
    protected function initializeBundles(): void
    {
        $this->bundles = [];

        foreach ($this->registerBundles() as $bundle) {
            $name = $bundle->getName();
            if (isset($this->bundles[$name])) {
                // Bundle is already registered, skip it
                continue;
            }

            if ($bundle instanceof PlatformConfigSectionInterface) {
                $key = $bundle->getConfigSectionKey();
                $section = $key !== ''
                    ? ($this->rawConfig[$key] ?? [])
                    : ($this->rawConfig['platform'] ?? []);
                $bundle->setPlatformRawConfig(self::toConfigArray($section));
            }

            $this->bundles[$name] = $bundle;
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $this->configureRoutesTrait($routes);

        $routes->import('.', '_solidworx_platform_auth_routes');
    }

    private function isTwoFactorEnabled(): bool
    {
        $platformConfig = $this->rawConfig['platform'] ?? [];
        if (! is_array($platformConfig)) {
            return false;
        }

        $security = $platformConfig['security'] ?? [];
        if (! is_array($security)) {
            return false;
        }

        $twoFactor = $security['two_factor'] ?? [];
        if (! is_array($twoFactor)) {
            return false;
        }

        return ($twoFactor['enabled'] ?? false) === true;
    }

    private function processPlatformConfig(): void
    {
        if ($this->rawConfig !== null) {
            return;
        }

        $this->rawConfig = [];

        $configFile = $this->resolveConfigFile();

        if ($configFile === null) {
            $this->publishPlatformConfigState();

            return;
        }

        $ext = pathinfo($configFile, PATHINFO_EXTENSION);

        $this->rawConfig = match ($ext) {
            'yaml', 'yml' => self::toConfigArray(Yaml::parseFile($configFile)),
            'json' => self::toConfigArray(json_decode((string) file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR)),
            'php' => self::toConfigArray(require $configFile),
            default => throw new RuntimeException(sprintf('Unsupported platform configuration file format: .%s', $ext)),
        };

        $this->publishPlatformConfigState();
    }

    /**
     * Normalises a decoded configuration value into a string-keyed map.
     *
     * The root of a configuration file is always an associative map, so any non-array
     * value is treated as empty and the keys are normalised to strings.
     *
     * @return array<string, mixed>
     */
    private static function toConfigArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $config = [];

        foreach ($value as $key => $item) {
            $config[(string) $key] = $item;
        }

        return $config;
    }

    /**
     * Publishes the parsed `platform:` section so compile-time helpers (e.g. the security
     * config helpers) can read it while the container is being built.
     */
    private function publishPlatformConfigState(): void
    {
        $platformConfig = $this->rawConfig['platform'] ?? [];

        PlatformConfigState::set(is_array($platformConfig) ? $platformConfig : []);
    }

    private function resolveConfigFile(): ?string
    {
        // Allow an explicit path override via environment variable
        $envOverride = $_ENV['PLATFORM_CONFIG_FILE'] ?? $_SERVER['PLATFORM_CONFIG_FILE'] ?? null;
        if (is_string($envOverride) && is_file($envOverride)) {
            return $envOverride;
        }

        $glob = $this->getProjectDir() . '/' . self::DEFAULT_CONFIG_GLOB;

        $configFiles = [];

        if (defined('GLOB_BRACE')) {
            $configFiles = glob($glob, GLOB_BRACE) ?: [];
        } else {
            foreach (['yml', 'yaml', 'json', 'php'] as $ext) {
                $candidate = $this->getProjectDir() . '/platform.' . $ext;
                if (file_exists($candidate)) {
                    $configFiles[] = $candidate;
                }
            }
        }

        if ($configFiles === []) {
            return null;
        }

        if (count($configFiles) > 1) {
            throw new RuntimeException(sprintf(
                'Multiple platform configuration files found: %s. Please ensure there is only one configuration file.',
                implode(', ', $configFiles)
            ));
        }

        return $configFiles[0];
    }
}
