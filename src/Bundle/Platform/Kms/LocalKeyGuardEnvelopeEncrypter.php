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

use RuntimeException;
use SensitiveParameter;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeDecrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeEncrypterInterface;

/**
 * Runs LocalKeyManager::check() before the first encryption or decryption.
 *
 * The check runs here, at runtime, rather than when the cache is warmed: a cache warmed during a
 * Docker build sees no KMS_DSN, and one warmed elsewhere would skip the check altogether. A failed
 * check is not remembered as done, so every later call fails too.
 */
final class LocalKeyGuardEnvelopeEncrypter implements EnvelopeEncrypterInterface, EnvelopeDecrypterInterface
{
    private bool $checked = false;

    public function __construct(
        private readonly EnvelopeEncrypterInterface & EnvelopeDecrypterInterface $inner,
        private readonly LocalKeyManager $localKeys,
    ) {
    }

    /**
     * @throws RuntimeException If a local master key is used outside dev and test without being allowed
     */
    public function encrypt(string $key, #[SensitiveParameter] string $plaintext, string $aad = ''): Envelope
    {
        $this->check();

        return $this->inner->encrypt($key, $plaintext, $aad);
    }

    /**
     * @throws RuntimeException If a local master key is used outside dev and test without being allowed
     */
    public function decrypt(Envelope $envelope, string $aad = ''): string
    {
        $this->check();

        return $this->inner->decrypt($envelope, $aad);
    }

    private function check(): void
    {
        if (! $this->checked) {
            $this->localKeys->check();
            $this->checked = true;
        }
    }
}
