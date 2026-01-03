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

namespace SolidWorx\Platform\SaasBundle\Feature;

use SolidWorx\Platform\SaasBundle\Enum\FeatureType;
use function is_array;
use function is_bool;
use function is_int;

final readonly class FeatureValue
{
    public const int UNLIMITED = -1;

    /**
     * @param array<mixed> $value
     */
    public function __construct(
        public string $key,
        public FeatureType $type,
        public int|bool|string|array $value,
    ) {
    }

    public function isUnlimited(): bool
    {
        return $this->type === FeatureType::INTEGER && $this->value === self::UNLIMITED;
    }

    public function isEnabled(): bool
    {
        return match ($this->type) {
            FeatureType::BOOLEAN => $this->value === true,
            FeatureType::INTEGER => $this->value !== 0,
            FeatureType::STRING => $this->value !== '',
            FeatureType::ARRAY => $this->value !== [],
        };
    }

    public function asInt(): int
    {
        if (is_int($this->value)) {
            return $this->value;
        }

        if (is_bool($this->value)) {
            return $this->value ? 1 : 0;
        }

        return (int) $this->value;
    }

    public function asBool(): bool
    {
        if (is_bool($this->value)) {
            return $this->value;
        }

        if (is_int($this->value)) {
            return $this->value !== 0;
        }

        return (bool) $this->value;
    }

    public function asString(): string
    {
        if (is_array($this->value)) {
            return implode(',', $this->value);
        }

        if (is_bool($this->value)) {
            return $this->value ? 'true' : 'false';
        }

        return (string) $this->value;
    }

    /**
     * @return array<mixed>
     */
    public function asArray(): array
    {
        if (is_array($this->value)) {
            return $this->value;
        }

        return [$this->value];
    }

    public function allows(int $currentUsage): bool
    {
        if ($this->isUnlimited()) {
            return true;
        }

        if ($this->type !== FeatureType::INTEGER) {
            return $this->isEnabled();
        }

        return $currentUsage < $this->asInt();
    }

    public function getRemainingQuota(int $currentUsage): ?int
    {
        if ($this->isUnlimited()) {
            return null;
        }

        if ($this->type !== FeatureType::INTEGER) {
            return null;
        }

        return max(0, $this->asInt() - $currentUsage);
    }
}
