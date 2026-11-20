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

use Psr\Log\LoggerInterface;
use RuntimeException;
use SensitiveParameter;
use Symfony\Component\KeyManagement\Dsn;
use function chmod;
use function dirname;
use function fclose;
use function file_exists;
use function fopen;
use function fwrite;
use function in_array;
use function is_dir;
use function is_string;
use function link;
use function mkdir;
use function random_bytes;
use function rename;
use function rtrim;
use function sodium_crypto_aead_xchacha20poly1305_ietf_keygen;
use function sprintf;
use function str_ends_with;
use function tempnam;
use function unlink;

/**
 * Looks after the master key when it is a local file rather than a remote KMS.
 *
 * In dev and test a missing key is generated, so encrypted columns work out of the box. Every other
 * environment is treated as production (fail closed): a local master key is refused unless
 * explicitly allowed, since whoever reads the file can decrypt every data key.
 */
final readonly class LocalKeyManager
{
    private const array LOCAL_SCHEMES = [
        'sodium', 'sodium+dir', 'sodium+fly',
        'openssl', 'openssl+dir', 'openssl+fly',
        'sodium-sealed-box', 'sodium-sealed-box+dir', 'sodium-sealed-box+fly',
    ];

    private const array GENERATABLE_SCHEMES = ['sodium+dir', 'openssl+dir'];

    /**
     * The only environments trusted with a generated local key; any other is treated as production.
     */
    public const array LOCAL_KEY_ENVIRONMENTS = ['dev', 'test'];

    private bool $storeSecretConfigured;

    /**
     * @param string|null $storeSecret KMS_STORE_SECRET; only whether it is set is kept
     */
    public function __construct(
        #[SensitiveParameter]
        private string $dsn,
        private string $keyId,
        private string $environment,
        private bool $allowLocalKeyInProd,
        private ?LoggerInterface $logger,
        #[SensitiveParameter]
        ?string $storeSecret,
    ) {
        $this->storeSecretConfigured = (string) $storeSecret !== '';
    }

    public function isLocal(): bool
    {
        return in_array($this->dsn()->scheme, self::LOCAL_SCHEMES, strict: true);
    }

    /**
     * @return string|null The key file of a `*+dir` DSN, null for any other DSN
     */
    public function keyPath(): ?string
    {
        $dsn = $this->dsn();

        if (! str_ends_with($dsn->scheme, '+dir')) {
            return null;
        }

        $extension = $dsn->getOption('ext', '');

        return rtrim($dsn->path, '/') . '/' . $this->keyId . (is_string($extension) ? $extension : '');
    }

    /**
     * Writes a new random master key, readable by its owner only.
     *
     * @return string The path of the key file
     *
     * @throws RuntimeException If the DSN is not sodium+dir:// or openssl+dir://, or the key already exists and $overwrite is false
     */
    public function generate(bool $overwrite = false): string
    {
        $scheme = $this->dsn()->scheme;
        $key = match ($scheme) {
            'sodium+dir' => sodium_crypto_aead_xchacha20poly1305_ietf_keygen(),
            'openssl+dir' => random_bytes(32),
            default => throw new RuntimeException(sprintf('Only sodium+dir:// and openssl+dir:// master keys can be generated; the KMS DSN uses "%s://". Configure the master key via KMS_DSN or platform.kms.dsn.', $scheme)),
        };

        $path = (string) $this->keyPath();
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0700, recursive: true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create the key directory "%s".', $directory));
        }

        if (! $overwrite && file_exists($path)) {
            throw new RuntimeException(sprintf('The master key "%s" already exists; overwriting it makes everything encrypted under it unreadable.', $path));
        }

        // tempnam() creates the file 0600, so the key is never readable by anyone else, even briefly.
        $temporary = tempnam($directory, '.' . $this->keyId);
        $handle = $temporary === false ? false : fopen($temporary, 'wb');

        if ($temporary === false || $handle === false || fwrite($handle, $key) !== 32 || ! fclose($handle) || ! chmod($temporary, 0600)) {
            if (is_string($temporary)) {
                @unlink($temporary);
            }

            throw new RuntimeException(sprintf('Cannot write the master key "%s".', $path));
        }

        // A link fails if the key appeared meanwhile; rename replaces it, which only --force asks for.
        $written = $overwrite ? rename($temporary, $path) : @link($temporary, $path);
        @unlink($temporary);

        if (! $written) {
            throw new RuntimeException(sprintf('Cannot write the master key "%s".', $path));
        }

        return $path;
    }

    /**
     * Outside dev and test, refuses a local master key unless allowed; in dev and test, generates a missing one.
     *
     * @throws RuntimeException If a local master key is used outside dev and test without being allowed
     */
    public function check(): void
    {
        if (! in_array($this->environment, self::LOCAL_KEY_ENVIRONMENTS, strict: true)) {
            if (! $this->allowLocalKeyInProd && $this->isLocal()) {
                throw new RuntimeException(sprintf('Refusing a local master key in the "%s" environment (only dev and test may use one): configure the master key via KMS_DSN or platform.kms.dsn to point at a remote KMS, or set platform.kms.allow_local_key_in_prod: true.', $this->environment));
            }

            if (! $this->storeSecretConfigured) {
                $this->logger?->warning('KMS_STORE_SECRET is not set, so stored data keys are authenticated with kernel.secret: rotating kernel.secret without keeping the old one makes every encrypted column unreadable.');
            }

            return;
        }

        $path = $this->keyPath();

        if ($path === null || file_exists($path)) {
            return;
        }

        $scheme = $this->dsn()->scheme;

        if (! in_array($scheme, self::GENERATABLE_SCHEMES, strict: true)) {
            $this->logger?->warning(sprintf('The master key %s is missing and %s:// keys cannot be generated; create it, or configure the master key via KMS_DSN or platform.kms.dsn.', $path, $scheme));

            return;
        }

        $this->generate();
    }

    private function dsn(): Dsn
    {
        return Dsn::fromString($this->dsn);
    }
}
