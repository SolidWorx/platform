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

namespace SolidWorx\Platform\PlatformBundle\Config;

interface SchemaGeneratorInterface
{
    /**
     * Build and return the full JSON Schema array for `platform.yaml`.
     *
     * @return array<string, mixed>
     */
    public function generate(): array;
}
