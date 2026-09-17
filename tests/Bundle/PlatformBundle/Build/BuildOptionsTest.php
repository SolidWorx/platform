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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Build;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Build\BuildOptions;

/**
 * @phpstan-import-type BuildConfig from BuildOptions
 */
#[CoversClass(BuildOptions::class)]
final class BuildOptionsTest extends TestCase
{
    public function testMapsConfigOntoProperties(): void
    {
        $options = BuildOptions::fromConfig($this->config(), 'v2.4.0', []);

        self::assertSame('Acme', $options->appName);
        self::assertSame('acme', $options->binaryName);
        self::assertSame('ACME', $options->envPrefix);
        self::assertSame('9000', $options->defaultPort);
        self::assertSame('v2.4.0', $options->version);
        self::assertSame('/app/build', $options->outputDir);
        self::assertSame('/app/var/build', $options->workDir);
        self::assertSame('acme:is-installed', $options->installCheckCommand);
        self::assertSame(['cache:clear'], $options->bootCommands);
    }

    public function testOverridesWinOverConfig(): void
    {
        $options = BuildOptions::fromConfig($this->config(), 'v2.4.0', [
            'output_dir' => '/tmp/out',
            'php_version' => '8.4',
        ]);

        self::assertSame('/tmp/out', $options->outputDir);
        self::assertSame('8.4', $options->phpVersion);
    }

    public function testNullOverridesAreIgnored(): void
    {
        $options = BuildOptions::fromConfig($this->config(), 'v2.4.0', [
            'output_dir' => null,
            'php_version' => null,
        ]);

        self::assertSame('/app/build', $options->outputDir);
        self::assertSame('8.5', $options->phpVersion);
    }

    public function testExtensionsStayEmptyWhenNothingIsConfigured(): void
    {
        $config = $this->config();
        $config['php']['extensions'] = [];
        $config['php']['add'] = [];
        $config['php']['remove'] = [];

        $options = BuildOptions::fromConfig($config, 'v1', []);

        self::assertSame([], $options->resolveExtensions(['bcmath', 'ssh2']));
    }

    public function testAddAndRemoveApplyToTheScriptDefaultsWhenNoExplicitListIsGiven(): void
    {
        $config = $this->config();
        $config['php']['extensions'] = [];
        $config['php']['add'] = ['excimer'];
        $config['php']['remove'] = ['ssh2'];

        $options = BuildOptions::fromConfig($config, 'v1', []);

        self::assertSame(['bcmath', 'excimer'], $options->resolveExtensions(['bcmath', 'ssh2']));
    }

    public function testExplicitListWinsOverScriptDefaults(): void
    {
        $config = $this->config();
        $config['php']['extensions'] = ['intl', 'pdo_mysql'];
        $config['php']['add'] = ['excimer'];
        $config['php']['remove'] = ['intl'];

        $options = BuildOptions::fromConfig($config, 'v1', []);

        self::assertSame(['pdo_mysql', 'excimer'], $options->resolveExtensions(['bcmath', 'ssh2']));
    }

    public function testDeduplicatesExtensions(): void
    {
        $config = $this->config();
        $config['php']['extensions'] = ['intl', 'intl'];
        $config['php']['add'] = ['intl'];
        $config['php']['remove'] = [];

        $options = BuildOptions::fromConfig($config, 'v1', []);

        self::assertSame(['intl'], $options->resolveExtensions([]));
    }

    public function testBinaryFileName(): void
    {
        $options = BuildOptions::fromConfig($this->config(), 'v1', []);

        self::assertSame('acme-mac-arm64', $options->binaryFileName('mac', 'arm64'));
    }

    /**
     * @return BuildConfig
     */
    private function config(): array
    {
        return [
            'name' => 'Acme',
            'description' => 'Acme does things',
            'binary_name' => 'acme',
            'env_prefix' => 'ACME',
            'default_port' => '9000',
            'output_dir' => '/app/build',
            'work_dir' => '/app/var/build',
            'php' => [
                'version' => '8.5',
                'extensions' => [],
                'add' => [],
                'remove' => [],
                'extension_libs' => ['libavif'],
            ],
            'exclude' => ['node_modules/'],
            'hooks' => [
                'install_check' => 'acme:is-installed',
                'on_boot' => ['cache:clear'],
            ],
        ];
    }
}
