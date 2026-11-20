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
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SolidWorx\Platform\PlatformBundle\Kms\LocalKeyManager;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;
use function array_column;
use function bin2hex;
use function class_exists;
use function clearstatcache;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function fileperms;
use function filesize;
use function random_bytes;
use function sys_get_temp_dir;

#[CoversClass(LocalKeyManager::class)]
#[RequiresPhpExtension('sodium')]
final class LocalKeyManagerTest extends TestCase
{
    private string $dir;

    #[Override]
    protected function setUp(): void
    {
        if (! class_exists(Encrypted::class)) {
            self::markTestSkipped('symfony/doctrine-orm-key-management is not installed.');
        }

        // A directory that does not exist yet, so generating has to create it.
        $this->dir = sys_get_temp_dir() . '/platform-kms-' . bin2hex(random_bytes(6)) . '/keys';
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove(dirname($this->dir));
    }

    public function testKeyPathOfADirectoryDsn(): void
    {
        $this->assertSame($this->dir . '/platform.key', $this->manager('dev')->keyPath());
        $this->assertNull($this->manager('dev', dsn: 'aws-kms://default?region=eu-west-1')->keyPath());
    }

    public function testIsLocal(): void
    {
        $this->assertTrue($this->manager('dev')->isLocal());
        $this->assertTrue($this->manager('dev', dsn: 'sodium://default?keys[platform]=abc')->isLocal());
        $this->assertTrue($this->manager('dev', dsn: 'openssl+dir:///etc/keys')->isLocal());
        $this->assertFalse($this->manager('dev', dsn: 'aws-kms://default?region=eu-west-1')->isLocal());
    }

    public function testGeneratesKeyInDev(): void
    {
        $this->manager('dev')->check();

        $path = $this->dir . '/platform.key';
        $this->assertFileExists($path);
        $this->assertSame(32, filesize($path));
        $this->assertSame(0600, fileperms($path) & 0777);
        $this->assertSame(0700, fileperms($this->dir) & 0777);
    }

    public function testGeneratesOpenSslKey(): void
    {
        $path = $this->manager('dev', dsn: 'openssl+dir://' . $this->dir . '?ext=.key')->generate();

        $this->assertSame($this->dir . '/platform.key', $path);
        $this->assertSame(32, filesize($path));
    }

    public function testDoesNotOverwrite(): void
    {
        $manager = $this->manager('dev');
        $path = $manager->generate();
        $key = (string) file_get_contents($path);

        $manager->check();

        clearstatcache();
        $this->assertSame($key, file_get_contents($path));

        $this->expectException(RuntimeException::class);
        $manager->generate();
    }

    public function testOverwritesWhenAsked(): void
    {
        $manager = $this->manager('dev');
        $path = $manager->generate();
        file_put_contents($path, 'old');

        $manager->generate(overwrite: true);

        $this->assertSame(32, filesize($path));
        $this->assertSame(0600, fileperms($path) & 0777);
    }

    public function testRefusesToGenerateForRemoteDsn(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('aws-kms');

        $this->manager('dev', dsn: 'aws-kms://default?region=eu-west-1')->generate();
    }

    public function testProdWithLocalKeyThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('configure the master key via KMS_DSN or platform.kms.dsn');

        $this->manager('prod')->check();
    }

    public function testProdWithInlineLocalKeyThrows(): void
    {
        $this->expectException(RuntimeException::class);

        $this->manager('prod', dsn: 'sodium://default?keys[platform]=abc')->check();
    }

    public function testProdWithLocalKeyAllowedPasses(): void
    {
        $this->expectNotToPerformAssertions();

        $this->manager('prod', allowLocalKeyInProd: true)->check();
    }

    public function testProdWithRemoteDsnPasses(): void
    {
        $this->expectNotToPerformAssertions();

        $this->manager('prod', dsn: 'aws-kms://default?region=eu-west-1')->check();
    }

    public function testProdWarnsWithoutStoreSecret(): void
    {
        $logger = new BufferingLogger();

        $this->manager('prod', dsn: 'aws-kms://default?region=eu-west-1', logger: $logger, storeSecret: '')->check();

        $this->assertSame(['warning'], array_column($logger->cleanLogs(), 0));
    }

    public function testProdDoesNotWarnWithStoreSecret(): void
    {
        $logger = new BufferingLogger();

        $this->manager('prod', dsn: 'aws-kms://default?region=eu-west-1', logger: $logger)->check();

        $this->assertSame([], $logger->cleanLogs());
    }

    public function testDevDoesNotWarnWithoutStoreSecret(): void
    {
        $logger = new BufferingLogger();

        $this->manager('dev', logger: $logger, storeSecret: null)->check();

        $this->assertSame([], $logger->cleanLogs());
    }

    public function testStagingWithLocalKeyThrows(): void
    {
        // Fail closed: only dev and test are trusted with a local key.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('local master key');

        $this->manager('staging')->check();
    }

    public function testStagingWarnsWithoutStoreSecret(): void
    {
        $logger = new BufferingLogger();

        $this->manager('staging', dsn: 'aws-kms://default?region=eu-west-1', logger: $logger, storeSecret: null)->check();

        $this->assertSame(['warning'], array_column($logger->cleanLogs(), 0));
    }

    public function testStagingNeverGenerates(): void
    {
        $this->manager('staging', allowLocalKeyInProd: true)->check();

        $this->assertFileDoesNotExist($this->dir . '/platform.key');
    }

    public function testGeneratesKeyInTest(): void
    {
        $this->manager('test')->check();

        $this->assertFileExists($this->dir . '/platform.key');
    }

    public function testDevWarnsInsteadOfGeneratingASealedBoxKey(): void
    {
        $logger = new BufferingLogger();

        $this->manager('dev', dsn: 'sodium-sealed-box+dir://' . $this->dir . '?ext=.key', logger: $logger)->check();

        $logs = $logger->cleanLogs();
        $this->assertSame(['warning'], array_column($logs, 0));
        $message = array_column($logs, 1)[0];
        $this->assertIsString($message);
        $this->assertStringContainsString('KMS_DSN or platform.kms.dsn', $message);
        $this->assertFileDoesNotExist($this->dir . '/platform.key');
    }

    public function testProdNeverGenerates(): void
    {
        $this->manager('prod', allowLocalKeyInProd: true)->check();

        $this->assertFileDoesNotExist($this->dir . '/platform.key');
    }

    private function manager(
        string $environment,
        ?string $dsn = null,
        bool $allowLocalKeyInProd = false,
        ?BufferingLogger $logger = null,
        ?string $storeSecret = 'store-secret',
    ): LocalKeyManager {
        return new LocalKeyManager(
            $dsn ?? 'sodium+dir://' . $this->dir . '?ext=.key',
            'platform',
            $environment,
            $allowLocalKeyInProd,
            $logger,
            $storeSecret,
        );
    }
}
