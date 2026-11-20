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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures;

use Override;
use SolidWorx\Platform\PlatformBundle\Kernel;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * A platform kernel reading its platform config from a throwaway project directory, with only
 * FrameworkBundle and a spy bundle registered.
 */
final class ConfigSectionTestKernel extends Kernel
{
    public readonly ConfigSectionSpyBundle $spy;

    public function __construct(
        private readonly string $projectDir,
        string $sectionKey,
    ) {
        $this->spy = new ConfigSectionSpyBundle($sectionKey);

        parent::__construct('test', debug: false);
    }

    /**
     * @return iterable<Bundle>
     */
    #[Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield $this->spy;
    }

    #[Override]
    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->projectDir . '/var/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->projectDir . '/var/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'config-section-test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
        ]);
    }
}
