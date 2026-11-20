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

namespace SolidWorx\Platform\PlatformBundle\Kms;

use LogicException;
use SensitiveParameter;
use SolidWorx\Platform\PlatformBundle\Kms\Exception\MissingTenantContextException;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeDecrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeEncrypterInterface;
use Symfony\Component\Uid\Ulid;
use function pack;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;
use function unpack;

/**
 * Encrypts the columns of tenant-aware entities under the data key of the current tenant.
 *
 * Every tenant-aware column of a tenant shares that tenant's data key, and the tenant is bound
 * into the AAD, so a value only decrypts for the tenant it was written for. Without a tenant,
 * reading or writing such a column throws: there is no fallback key and no way around it.
 *
 * Anything else, a shared column or a caller that is not Symfony's EncryptedType, passes through.
 */
final readonly class TenantEnvelopeEncrypter implements EnvelopeEncrypterInterface, EnvelopeDecrypterInterface
{
    public const string SCOPE_PREFIX = 'platform.tenant:';

    /**
     * How Symfony's EncryptedType starts the AAD of a column: this label, then the length-prefixed key.
     */
    private const string ENCRYPTED_TYPE_LABEL = 'symfony/key-management/doctrine-dbal/encrypted-type/v1';

    private const string TENANT_LABEL = 'solidworx/platform/kms/tenant/v1';

    public function __construct(
        private EnvelopeEncrypterInterface & EnvelopeDecrypterInterface $inner,
        private TenantScopeRegistry $scopes,
        private TenantContext $tenantContext,
    ) {
    }

    public function encrypt(string $key, #[SensitiveParameter] string $plaintext, string $aad = ''): Envelope
    {
        if (! $this->scopes->isTenantScope($key)) {
            if (str_starts_with($key, self::SCOPE_PREFIX)) {
                throw new LogicException(sprintf('The encryption scope "%s" is reserved for the data keys of tenants.', $key));
            }

            return $this->inner->encrypt($key, $plaintext, $aad);
        }

        $tenant = $this->tenant($key);

        return $this->inner->encrypt(self::SCOPE_PREFIX . $tenant->toBase32(), $plaintext, $aad . $this->tenantAad($tenant));
    }

    public function decrypt(Envelope $envelope, string $aad = ''): string
    {
        $scope = $this->encryptedTypeScope($aad);

        if ($scope === null || ! $this->scopes->isTenantScope($scope)) {
            return $this->inner->decrypt($envelope, $aad);
        }

        return $this->inner->decrypt($envelope, $aad . $this->tenantAad($this->tenant($scope)));
    }

    private function tenant(string $scope): Ulid
    {
        return $this->tenantContext->getTenantId() ?? throw new MissingTenantContextException($scope);
    }

    private function tenantAad(Ulid $tenant): string
    {
        return self::TENANT_LABEL . pack('n', 16) . $tenant->toBinary();
    }

    /**
     * Reads the key out of an EncryptedType AAD: label, n-packed key length, key, n-packed type length, type.
     *
     * @return string|null Null when the AAD is not EncryptedType's, i.e. the caller is not a Doctrine column
     *
     * @throws LogicException If the AAD starts like EncryptedType's but does not match its format
     */
    private function encryptedTypeScope(string $aad): ?string
    {
        if (! str_starts_with($aad, self::ENCRYPTED_TYPE_LABEL)) {
            return null;
        }

        $offset = strlen(self::ENCRYPTED_TYPE_LABEL);
        $key = $this->lengthPrefixed($aad, $offset);
        $type = $key === null ? null : $this->lengthPrefixed($aad, $offset);

        if ($key === null || $type === null || $offset !== strlen($aad)) {
            // Fail closed: a format change upstream must not turn tenant columns into shared ones.
            throw new LogicException("The AAD of an encrypted Doctrine column does not match the format of Symfony's EncryptedType; its encryption scope cannot be read.");
        }

        return $key;
    }

    private function lengthPrefixed(string $bytes, int &$offset): ?string
    {
        if (strlen($bytes) < $offset + 2) {
            return null;
        }

        /** @var array{1: int} $unpacked */
        $unpacked = unpack('n', $bytes, $offset);
        $length = $unpacked[1];

        if (strlen($bytes) < $offset + 2 + $length) {
            return null;
        }

        $value = substr($bytes, $offset + 2, $length);
        $offset += 2 + $length;

        return $value;
    }
}
