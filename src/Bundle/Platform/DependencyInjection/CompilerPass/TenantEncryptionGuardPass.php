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

namespace SolidWorx\Platform\PlatformBundle\DependencyInjection\CompilerPass;

use LogicException;
use Override;
use SolidWorx\Platform\PlatformBundle\Kms\TenantEnvelopeEncrypter;
use Symfony\Component\DependencyInjection\Argument\LazyProxyArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use function sprintf;

/**
 * Refuses to build a multi-tenant container whose encrypted Doctrine columns would not go through
 * TenantEnvelopeEncrypter, e.g. platform.kms.enabled: false while the application configures
 * key_management itself: Symfony would then encrypt tenant-aware columns under a shared key.
 *
 * Runs after KeyManagementBundle's RegisterEncryptedColumnsPass, which only wires an encrypter into
 * the encrypted types and the column listener when a column is encrypted, so an application that
 * encrypts nothing is left alone.
 */
final class TenantEncryptionGuardPass implements CompilerPassInterface
{
    private const string TYPES = 'key_management.doctrine_dbal.encrypted_types';

    private const string LISTENER = 'key_management.encrypted_column_listener';

    private const string STORED_ENCRYPTER = 'key_management.stored_envelope_encrypter';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasParameter('solidworx_platform.multi_tenancy.enabled')
            || $container->getParameter('solidworx_platform.multi_tenancy.enabled') !== true) {
            return;
        }

        // The encrypter of the columns with an explicit key (and of configured types), and the one
        // of the columns that omit it. Neither is set when no column is encrypted.
        $encrypters = [];

        if ($container->hasDefinition(self::TYPES)) {
            $encrypters[] = $container->getDefinition(self::TYPES)->getArguments()[0] ?? null;
        }

        if ($container->hasDefinition(self::LISTENER) && isset($container->getDefinition(self::LISTENER)->getArguments()[1])) {
            $encrypters[] = $container->getDefinition(self::LISTENER)->getArguments()[1];
        }

        if ($encrypters === []) {
            return;
        }

        $scoped = $container->hasDefinition(TenantEnvelopeEncrypter::class)
            && ($container->getDefinition(TenantEnvelopeEncrypter::class)->getDecoratedService()[0] ?? null) === self::STORED_ENCRYPTER;

        foreach ($encrypters as $encrypter) {
            if ($encrypter instanceof LazyProxyArgument) {
                $encrypter = $encrypter->getValues()[0] ?? null;
            }

            $scoped = $scoped && $encrypter instanceof Reference && (string) $encrypter === self::STORED_ENCRYPTER;
        }

        if (! $scoped) {
            throw new LogicException(sprintf('Multi-tenancy is enabled and Doctrine columns are encrypted, but not through the platform\'s per-tenant encrypter, so tenant-aware columns would be encrypted under a shared key. Let the platform configure key_management: do not set platform.kms.enabled: false, and install doctrine/orm ^3 and doctrine/dbal ^4.6; the platform then decorates "%s", which the encrypted columns must use.', self::STORED_ENCRYPTER));
        }
    }
}
