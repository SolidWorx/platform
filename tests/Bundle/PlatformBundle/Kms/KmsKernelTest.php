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

use LogicException;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Kms\LocalKeyGuardEnvelopeEncrypter;
use SolidWorx\Platform\PlatformBundle\Kms\TenantEnvelopeEncrypter;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Kms\Kernel\KmsTestKernel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use function class_exists;
use function sys_get_temp_dir;

/**
 * Boots a real kernel, so the order of the compiler passes and decorators is the one an
 * application gets, not one a test assembled by hand.
 */
#[CoversNothing]
#[RequiresPhpExtension('sodium')]
#[RequiresPhpExtension('pdo_sqlite')]
final class KmsKernelTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        if (! class_exists(Encrypted::class)) {
            self::markTestSkipped('symfony/doctrine-orm-key-management is not installed.');
        }

        new Filesystem()->remove(sys_get_temp_dir() . '/solidworx_kms_kernel_test');
    }

    public function testTheStoredEncrypterReachesTheTenantEncrypter(): void
    {
        $kernel = new KmsTestKernel([
            'multi_tenancy' => [
                'enabled' => true,
            ],
        ]);
        $kernel->boot();
        $chain = $kernel->getContainer()->getParameter(KmsTestKernel::CHAIN);
        $kernel->shutdown();

        $this->assertSame([LocalKeyGuardEnvelopeEncrypter::class, TenantEnvelopeEncrypter::class, StoredEnvelopeEncrypter::class], $chain);
    }

    /**
     * With the platform's encryption off and key_management configured by the application, the
     * tenant-aware columns of the fixtures would be encrypted under a shared key.
     */
    public function testEncryptedColumnsBypassingTheTenantEncrypterFailTheBuild(): void
    {
        $kernel = new KmsTestKernel([
            'multi_tenancy' => [
                'enabled' => true,
            ],
            'kms' => [
                'enabled' => false,
            ],
        ], [
            'clients' => [
                'app' => 'sodium+dir://' . sys_get_temp_dir() . '/solidworx_kms_kernel_test?ext=.key',
            ],
            'store' => [
                'key_id' => 'app',
            ],
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('shared key');

        $kernel->boot();
    }
}
