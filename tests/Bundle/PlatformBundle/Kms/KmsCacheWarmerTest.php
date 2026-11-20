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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Kms;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Kms\KmsCacheWarmer;
use SolidWorx\Platform\PlatformBundle\Kms\LocalKeyManager;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;
use function array_column;
use function bin2hex;
use function class_exists;
use function random_bytes;
use function sys_get_temp_dir;

#[CoversClass(KmsCacheWarmer::class)]
#[UsesClass(LocalKeyManager::class)]
#[RequiresPhpExtension('sodium')]
final class KmsCacheWarmerTest extends TestCase
{
    private string $dir;

    #[Override]
    protected function setUp(): void
    {
        if (! class_exists(Encrypted::class)) {
            self::markTestSkipped('symfony/doctrine-orm-key-management is not installed.');
        }

        $this->dir = sys_get_temp_dir() . '/platform-kms-' . bin2hex(random_bytes(6));
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->dir);
    }

    public function testIsNotOptional(): void
    {
        $this->assertFalse(new KmsCacheWarmer('dev', fn (): LocalKeyManager => $this->manager('dev'))->isOptional());
    }

    public function testWarmerGeneratesKeyInDev(): void
    {
        new KmsCacheWarmer('dev', fn (): LocalKeyManager => $this->manager('dev'))->warmUp($this->dir);

        $this->assertFileExists($this->dir . '/platform.key');
    }

    public function testWarmerNeverThrowsInProd(): void
    {
        // A local key in prod without allow_local_key_in_prod: the guard refuses it at runtime, the warmer does not.
        $this->assertSame([], new KmsCacheWarmer('prod', fn (): LocalKeyManager => $this->manager('prod'))->warmUp($this->dir));

        // An image built without its runtime env vars: KMS_DSN cannot even be resolved.
        $this->assertSame([], new KmsCacheWarmer('prod', static fn (): never => throw new EnvNotFoundException('Environment variable not found: "KMS_DSN".'))->warmUp($this->dir));

        $this->assertFileDoesNotExist($this->dir . '/platform.key');
    }

    public function testWarmerNeverGeneratesInStaging(): void
    {
        $this->assertSame([], new KmsCacheWarmer('staging', fn (): LocalKeyManager => $this->manager('staging'))->warmUp($this->dir));

        $this->assertFileDoesNotExist($this->dir . '/platform.key');
    }

    public function testWarmerWarnsInsteadOfThrowingForASealedBoxKey(): void
    {
        $logger = new BufferingLogger();
        $manager = new LocalKeyManager('sodium-sealed-box+dir://' . $this->dir . '?ext=.key', 'platform', 'dev', false, $logger, null);

        $this->assertSame([], new KmsCacheWarmer('dev', static fn (): LocalKeyManager => $manager)->warmUp($this->dir));

        $this->assertSame(['warning'], array_column($logger->cleanLogs(), 0));
    }

    private function manager(string $environment): LocalKeyManager
    {
        return new LocalKeyManager('sodium+dir://' . $this->dir . '?ext=.key', 'platform', $environment, false, null, null);
    }
}
