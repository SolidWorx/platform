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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\SolidWorxPlatformExtension;
use SolidWorx\Platform\PlatformBundle\Kms\Command\GenerateKeyCommand;
use SolidWorx\Platform\PlatformBundle\Kms\KmsCacheWarmer;
use SolidWorx\Platform\PlatformBundle\Kms\KmsSupport;
use SolidWorx\Platform\PlatformBundle\Kms\LocalKeyGuardEnvelopeEncrypter;
use SolidWorx\Platform\PlatformBundle\Kms\LocalKeyManager;
use SolidWorx\Platform\PlatformBundle\Kms\TenantEnvelopeEncrypter;
use SolidWorx\Platform\PlatformBundle\Kms\TenantScopeRegistry;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;

#[CoversClass(SolidWorxPlatformExtension::class)]
#[CoversClass(KmsSupport::class)]
#[RequiresPhpExtension('sodium')]
final class KmsConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        if (! class_exists(Encrypted::class)) {
            self::markTestSkipped('symfony/doctrine-orm-key-management is not installed.');
        }
    }

    public function testDefaultsPrependKeyManagementConfig(): void
    {
        $container = $this->prepend([]);

        $this->assertSame([[
            'clients' => [
                'platform' => '%env(default:solidworx_platform.kms.default_dsn:KMS_DSN)%',
            ],
            'default_client' => 'platform',
            'store' => [
                'key_id' => 'platform',
                'client' => 'platform',
                'secret' => '%env(default:kernel.secret:KMS_STORE_SECRET)%',
            ],
        ]], $container->getExtensionConfig('key_management'));
    }

    public function testAppConfigWins(): void
    {
        $container = $this->prepend([], appKeyManagement: [
            'store' => [
                'key_id' => 'other',
            ],
        ]);

        $configs = $container->getExtensionConfig('key_management');

        // Prepended platform defaults come first, so the application's config is merged over them.
        $this->assertCount(2, $configs);
        $this->assertSame([
            'store' => [
                'key_id' => 'other',
            ],
        ], $configs[1]);
    }

    public function testDisabledPrependsNothing(): void
    {
        $container = $this->prepend([
            'kms' => [
                'enabled' => false,
            ],
        ]);

        $this->assertSame([], $container->getExtensionConfig('key_management'));
    }

    public function testParametersAreRegistered(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', value: false);
        $container->setParameter('kernel.environment', 'test');
        new SolidWorxPlatformExtension([])->load([[]], $container);

        $this->assertTrue($container->getParameter('solidworx_platform.kms.enabled'));
        $this->assertSame('%env(default:solidworx_platform.kms.default_dsn:KMS_DSN)%', $container->getParameter('solidworx_platform.kms.dsn'));
        $this->assertSame('platform', $container->getParameter('solidworx_platform.kms.key_id'));
        $this->assertFalse($container->getParameter('solidworx_platform.kms.allow_local_key_in_prod'));
        $this->assertSame('sodium+dir://%kernel.project_dir%/config/kms?ext=.key', $container->getParameter('solidworx_platform.kms.default_dsn'));
    }

    public function testTenantScopeRegistryIsRegisteredWithMultiTenancy(): void
    {
        $definition = $this->load([
            'multi_tenancy' => [
                'enabled' => true,
            ],
        ])->getDefinition(TenantScopeRegistry::class);

        $this->assertEquals([new Reference('doctrine'), new Reference('cache.system')], $definition->getArguments());
    }

    public function testTenantScopeRegistryIsNotCachedInDebug(): void
    {
        $definition = $this->load([
            'multi_tenancy' => [
                'enabled' => true,
            ],
        ], debug: true)->getDefinition(TenantScopeRegistry::class);

        $this->assertEquals([new Reference('doctrine'), null], $definition->getArguments());
    }

    public function testTenantScopeRegistryIsRemovedWithoutMultiTenancy(): void
    {
        $this->assertFalse($this->load([])->hasDefinition(TenantScopeRegistry::class));
    }

    public function testTenantScopeRegistryIsRemovedWhenKmsIsDisabled(): void
    {
        $container = $this->load([
            'multi_tenancy' => [
                'enabled' => true,
            ],
            'kms' => [
                'enabled' => false,
            ],
        ]);

        $this->assertFalse($container->hasDefinition(TenantScopeRegistry::class));
    }

    public function testTenantEnvelopeEncrypterDecoratesTheStoredEncrypter(): void
    {
        $definition = $this->load([
            'multi_tenancy' => [
                'enabled' => true,
            ],
        ])->getDefinition(TenantEnvelopeEncrypter::class);

        $this->assertSame(['key_management.stored_envelope_encrypter', null, 10], $definition->getDecoratedService());
        $this->assertEquals([
            new Reference('.inner'),
            new Reference(TenantScopeRegistry::class),
            new Reference(TenantContext::class),
        ], $definition->getArguments());
    }

    public function testTenantEnvelopeEncrypterIsRemovedWithoutMultiTenancy(): void
    {
        $this->assertFalse($this->load([])->hasDefinition(TenantEnvelopeEncrypter::class));
    }

    public function testTenantEnvelopeEncrypterIsRemovedWhenKmsIsDisabled(): void
    {
        $container = $this->load([
            'multi_tenancy' => [
                'enabled' => true,
            ],
            'kms' => [
                'enabled' => false,
            ],
        ]);

        $this->assertFalse($container->hasDefinition(TenantEnvelopeEncrypter::class));
    }

    public function testLocalKeyManagerIsWired(): void
    {
        $definition = $this->load([])->getDefinition(LocalKeyManager::class);

        $this->assertEquals([
            '%solidworx_platform.kms.dsn%',
            '%solidworx_platform.kms.key_id%',
            '%kernel.environment%',
            '%solidworx_platform.kms.allow_local_key_in_prod%',
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            '%env(default::KMS_STORE_SECRET)%',
        ], $definition->getArguments());
    }

    public function testLocalKeyGuardDecoratesTheStoredEncrypterWithoutMultiTenancy(): void
    {
        $definition = $this->load([])->getDefinition(LocalKeyGuardEnvelopeEncrypter::class);

        // Between the traceable wrapper (0) and the tenant decorator (10).
        $this->assertSame(['key_management.stored_envelope_encrypter', null, 5], $definition->getDecoratedService());
        $this->assertEquals([new Reference('.inner'), new Reference(LocalKeyManager::class)], $definition->getArguments());
    }

    public function testCacheWarmerResolvesTheManagerLazily(): void
    {
        $definition = $this->load([])->getDefinition(KmsCacheWarmer::class);

        $this->assertEquals([
            '%kernel.environment%',
            new ServiceClosureArgument(new Reference(LocalKeyManager::class)),
        ], $definition->getArguments());
    }

    public function testLocalKeyServicesAreRemovedWhenKmsIsDisabled(): void
    {
        $container = $this->load([
            'kms' => [
                'enabled' => false,
            ],
        ]);

        $this->assertFalse($container->hasDefinition(LocalKeyManager::class));
        $this->assertFalse($container->hasDefinition(LocalKeyGuardEnvelopeEncrypter::class));
        $this->assertFalse($container->hasDefinition(KmsCacheWarmer::class));
        $this->assertFalse($container->hasDefinition(GenerateKeyCommand::class));
    }

    public function testEnabledTrueWithoutBridgeThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('doctrine-orm-key-management');

        $this->prepend([
            'kms' => [
                'enabled' => true,
            ],
        ], kmsAvailable: false);
    }

    public function testAutoDisablesWhenUnavailable(): void
    {
        $container = $this->prepend([], kmsAvailable: false);

        $this->assertSame([], $container->getExtensionConfig('key_management'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config, bool $debug = false): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', $debug);
        $container->setParameter('kernel.environment', 'test');
        new SolidWorxPlatformExtension($config)->load([[]], $container);

        return $container;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $appKeyManagement
     */
    private function prepend(array $config, ?bool $kmsAvailable = null, array $appKeyManagement = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles_metadata', []);
        $container->registerExtension($this->keyManagementExtension());

        if ($appKeyManagement !== []) {
            $container->loadFromExtension('key_management', $appKeyManagement);
        }

        new SolidWorxPlatformExtension($config, $kmsAvailable)->prepend($container);

        return $container;
    }

    private function keyManagementExtension(): Extension
    {
        return new class() extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'key_management';
            }
        };
    }
}
