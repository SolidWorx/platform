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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SolidWorx\Platform\PlatformBundle\Config\Builder\PlatformConfigBuilder;
use SolidWorx\Platform\PlatformBundle\Kernel;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\ConfigSectionTestKernel;
use Symfony\Component\Filesystem\Filesystem;
use function sys_get_temp_dir;
use function uniqid;

/**
 * Bundles with their own section key (saas, ui) read a root-level sibling of `platform`.
 */
#[CoversClass(Kernel::class)]
#[UsesClass(PlatformConfigBuilder::class)]
final class KernelConfigSectionTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/solidworx_kernel_config_section_' . uniqid();
        new Filesystem()->mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    public function testPhpBuilderSectionReachesItsBundle(): void
    {
        new Filesystem()->dumpFile($this->projectDir . '/platform.php', <<<'PHP'
            <?php

            use SolidWorx\Platform\PlatformBundle\Config\Builder\PlatformConfigBuilder;

            return PlatformConfigBuilder::create()
                ->name('Builder App')
                ->withUiConfig(['icon_pack' => 'tabler'])
                ->build();
            PHP);

        $kernel = new ConfigSectionTestKernel($this->projectDir, 'ui');
        $kernel->boot();

        $this->assertSame([
            'icon_pack' => 'tabler',
        ], $kernel->spy->receivedConfig);

        $kernel->shutdown();
    }

    public function testSectionNestedUnderPlatformIsRejectedWithTheFix(): void
    {
        new Filesystem()->dumpFile($this->projectDir . '/platform.yaml', <<<'YAML'
            platform:
              name: 'Nested App'
              saas:
                features: {}
            YAML);

        $kernel = new ConfigSectionTestKernel($this->projectDir, 'saas');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Move "platform.saas" to "saas".');

        $kernel->boot();
    }
}
