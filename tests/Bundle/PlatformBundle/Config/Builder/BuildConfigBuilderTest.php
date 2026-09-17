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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Config\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Config\Builder\BuildConfigBuilder;
use SolidWorx\Platform\PlatformBundle\Config\Builder\PlatformConfigBuilder;

#[CoversClass(BuildConfigBuilder::class)]
final class BuildConfigBuilderTest extends TestCase
{
    public function testBuildsNestedConfig(): void
    {
        $config = PlatformConfigBuilder::create()
            ->name('Acme')
            ->build()
                ->binaryName('acme')
                ->envPrefix('ACME')
                ->defaultPort('9000')
                ->phpVersion('8.5')
                ->addExtensions('excimer')
                ->removeExtensions('ssh2', 'memcached')
                ->exclude('node_modules/', 'tests/')
                ->installCheck('acme:is-installed')
                ->onBoot('cache:clear', 'acme:keys:generate')
            ->end()
            ->toArray();

        self::assertSame([
            'binary_name' => 'acme',
            'env_prefix' => 'ACME',
            'default_port' => '9000',
            'php' => [
                'version' => '8.5',
                'add' => ['excimer'],
                'remove' => ['ssh2', 'memcached'],
            ],
            'exclude' => ['node_modules/', 'tests/'],
            'hooks' => [
                'install_check' => 'acme:is-installed',
                'on_boot' => ['cache:clear', 'acme:keys:generate'],
            ],
        ], self::build($config));
    }

    public function testOmittedValuesAreAbsent(): void
    {
        $config = PlatformConfigBuilder::create()
            ->build()
                ->binaryName('acme')
            ->end()
            ->toArray();

        self::assertSame([
            'binary_name' => 'acme',
        ], self::build($config));
    }

    /**
     * Narrows the `platform.build` section out of the raw builder array, which is typed
     * `array<string, mixed>` — PHPStan cannot follow the nested offset access otherwise.
     *
     * @param array<string, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private static function build(array $config): array
    {
        $platform = $config['platform'];
        self::assertIsArray($platform);

        $build = $platform['build'];
        self::assertIsArray($build);

        return $build;
    }
}
