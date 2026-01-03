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

final readonly class FeatureConfig
{
    /**
     * @param array<mixed> $defaultValue
     */
    public function __construct(
        public string $key,
        public FeatureType $type,
        public int|bool|string|array $defaultValue,
        public string $description = '',
    ) {
    }

    public function toFeatureValue(): FeatureValue
    {
        return new FeatureValue($this->key, $this->type, $this->defaultValue);
    }
}
