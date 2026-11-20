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

use Closure;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Generates a missing local master key outside production, so encrypted columns work out of the box.
 *
 * In production it does nothing: the cache may be warmed without the runtime env vars (a Docker
 * build), so the manager is not even built here. LocalKeyGuardEnvelopeEncrypter enforces the
 * production rules at runtime instead.
 */
final readonly class KmsCacheWarmer implements CacheWarmerInterface
{
    /**
     * @param Closure(): LocalKeyManager $localKeys Lazy, so resolving KMS_DSN cannot fail a production warm-up
     */
    public function __construct(
        private string $environment,
        private Closure $localKeys,
    ) {
    }

    public function isOptional(): bool
    {
        return false;
    }

    /**
     * @return list<string>
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if ($this->environment !== 'prod') {
            ($this->localKeys)()->check();
        }

        return [];
    }
}
