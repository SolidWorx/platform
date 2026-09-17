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

namespace SolidWorx\Platform\PlatformBundle\Build;

/**
 * A single thing a {@see Preflight} check found wrong, with the fix a developer should apply.
 */
final readonly class Problem
{
    public function __construct(
        public Severity $severity,
        public string $title,
        public string $detail,
    ) {
    }

    public static function error(string $title, string $detail): self
    {
        return new self(Severity::Error, $title, $detail);
    }

    public static function warning(string $title, string $detail): self
    {
        return new self(Severity::Warning, $title, $detail);
    }

    public function isError(): bool
    {
        return $this->severity === Severity::Error;
    }
}
