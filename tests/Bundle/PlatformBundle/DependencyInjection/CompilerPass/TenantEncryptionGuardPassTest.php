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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\DependencyInjection\CompilerPass;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\CompilerPass\TenantEncryptionGuardPass;
use SolidWorx\Platform\PlatformBundle\Kms\TenantEnvelopeEncrypter;
use Symfony\Component\DependencyInjection\Argument\LazyProxyArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use function class_exists;

#[CoversClass(TenantEncryptionGuardPass::class)]
final class TenantEncryptionGuardPassTest extends TestCase
{
    /**
     * platform.kms.enabled: false while the application configures key_management itself.
     */
    public function testEncryptedColumnsWithoutTheTenantEncrypterThrow(): void
    {
        $container = $this->container(multiTenancy: true, encryptedTypes: 'key_management.stored_envelope_encrypter', tenantEncrypter: false);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('platform.kms.enabled');

        new TenantEncryptionGuardPass()->process($container);
    }

    public function testEncryptedColumnsOutsideTheStoreThrow(): void
    {
        $container = $this->container(multiTenancy: true, encryptedTypes: 'key_management.envelope_encrypter.platform', tenantEncrypter: true);

        $this->expectException(LogicException::class);

        new TenantEncryptionGuardPass()->process($container);
    }

    public function testTheTenantEncrypterOnTheStorePasses(): void
    {
        $container = $this->container(multiTenancy: true, encryptedTypes: 'key_management.stored_envelope_encrypter', tenantEncrypter: true);

        $this->expectNotToPerformAssertions();

        new TenantEncryptionGuardPass()->process($container);
    }

    /**
     * Columns that omit their key are encrypted by the column listener, not by the types service.
     */
    public function testColumnsWithoutAKeyAndWithoutTheTenantEncrypterThrow(): void
    {
        $container = $this->container(multiTenancy: true, encryptedTypes: null, tenantEncrypter: false);
        $container->register('key_management.encrypted_column_listener')
            ->setArguments([[], new LazyProxyArgument(new Reference('key_management.stored_envelope_encrypter'))]);

        $this->expectException(LogicException::class);

        new TenantEncryptionGuardPass()->process($container);
    }

    public function testColumnsWithoutAKeyThroughTheTenantEncrypterPass(): void
    {
        $container = $this->container(multiTenancy: true, encryptedTypes: null, tenantEncrypter: true);
        $container->register('key_management.encrypted_column_listener')
            ->setArguments([[], new LazyProxyArgument(new Reference('key_management.stored_envelope_encrypter'))]);

        $this->expectNotToPerformAssertions();

        new TenantEncryptionGuardPass()->process($container);
    }

    public function testNothingIsCheckedWithoutMultiTenancy(): void
    {
        $container = $this->container(multiTenancy: false, encryptedTypes: 'key_management.stored_envelope_encrypter', tenantEncrypter: false);

        $this->expectNotToPerformAssertions();

        new TenantEncryptionGuardPass()->process($container);
    }

    /**
     * Without key management, or with it but no encrypted column, the types service does not exist.
     */
    public function testNothingIsCheckedWithoutEncryptedColumns(): void
    {
        $container = $this->container(multiTenancy: true, encryptedTypes: null, tenantEncrypter: false);
        // The listener exists with the bridge, but gets no encrypter while no column omits its key.
        $container->register('key_management.encrypted_column_listener')->setArguments([[]]);

        $this->expectNotToPerformAssertions();

        new TenantEncryptionGuardPass()->process($container);
    }

    private function container(bool $multiTenancy, ?string $encryptedTypes, bool $tenantEncrypter): ContainerBuilder
    {
        if (! class_exists(LazyProxyArgument::class)) {
            self::markTestSkipped('Encrypted columns need symfony/dependency-injection 8.2.');
        }

        $container = new ContainerBuilder();
        $container->setParameter('solidworx_platform.multi_tenancy.enabled', $multiTenancy);

        if ($encryptedTypes !== null) {
            $container->register('key_management.doctrine_dbal.encrypted_types')
                ->setArguments([new LazyProxyArgument(new Reference($encryptedTypes)), []]);
        }

        if ($tenantEncrypter) {
            $container->register(TenantEnvelopeEncrypter::class)
                ->setDecoratedService('key_management.stored_envelope_encrypter', priority: 10);
        }

        return $container;
    }
}
