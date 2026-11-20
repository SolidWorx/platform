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

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\DependencyInjection\SolidWorxPlatformExtension;
use SolidWorx\Platform\PlatformBundle\Kms\TenantEnvelopeEncrypter;
use SolidWorx\Platform\PlatformBundle\Kms\TenantScopeRegistry;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;
use Symfony\Component\KeyManagement\Debug\TraceableEnvelopeEncrypter;
use Symfony\Component\KeyManagement\KeyManagementBundle;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use function class_exists;

/**
 * Compiles KeyManagementBundle's own services with the platform's decorator, so the decoration
 * order is the one a real kernel produces.
 */
#[CoversClass(SolidWorxPlatformExtension::class)]
#[RequiresPhpExtension('sodium')]
final class TenantEnvelopeEncrypterWiringTest extends TestCase
{
    protected function setUp(): void
    {
        if (! class_exists(Encrypted::class)) {
            self::markTestSkipped('symfony/doctrine-orm-key-management is not installed.');
        }
    }

    public function testTraceableWrapperStaysOutermostInDebug(): void
    {
        $chain = $this->decorationChain(debug: true);

        $this->assertSame([TraceableEnvelopeEncrypter::class, TenantEnvelopeEncrypter::class, StoredEnvelopeEncrypter::class], $chain);
    }

    public function testDecoratorWrapsTheStoredEncrypterWithoutDebug(): void
    {
        $chain = $this->decorationChain(debug: false);

        $this->assertSame([TenantEnvelopeEncrypter::class, StoredEnvelopeEncrypter::class], $chain);
    }

    /**
     * @return list<string|null>
     */
    private function decorationChain(bool $debug): array
    {
        $platform = new ContainerBuilder();
        $platform->setParameter('kernel.debug', $debug);
        $platform->setParameter('kernel.environment', 'test');
        new SolidWorxPlatformExtension([
            'multi_tenancy' => [
                'enabled' => true,
            ],
        ])->load([[]], $platform);

        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', $debug);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.secret', 'secret');
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->setParameter('kernel.bundles_metadata', []);

        $bundle = new KeyManagementBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);
        $container->registerExtension($extension);
        $container->loadFromExtension('key_management', [
            'clients' => [
                'platform' => 'sodium+dir://' . __DIR__ . '?ext=.key',
            ],
            'default_client' => 'platform',
            'store' => [
                'key_id' => 'platform',
                'client' => 'platform',
            ],
        ]);
        $bundle->build($container);

        // What the profiler, DoctrineBundle and the platform's own services would provide.
        $container->register('profiler', stdClass::class)->setSynthetic(true);
        $container->register('doctrine.dbal.default_connection', Connection::class)->setSynthetic(true);
        $container->register(TenantScopeRegistry::class, TenantScopeRegistry::class)->setSynthetic(true);
        $container->register(TenantContext::class, TenantContext::class)->setSynthetic(true);
        $container->setDefinition(TenantEnvelopeEncrypter::class, $platform->getDefinition(TenantEnvelopeEncrypter::class));
        $container->setAlias('test.stored_envelope_encrypter', 'key_management.stored_envelope_encrypter')->setPublic(true);

        $container->compile();

        $chain = [];
        $definition = $container->findDefinition('test.stored_envelope_encrypter');

        while (true) {
            $chain[] = $definition->getClass();
            $inner = $definition->getArguments()[0] ?? null;

            if ($inner instanceof Reference) {
                $inner = $container->findDefinition((string) $inner);
            }

            if (! $inner instanceof Definition || $definition->getClass() === StoredEnvelopeEncrypter::class) {
                break;
            }

            $definition = $inner;
        }

        return $chain;
    }
}
