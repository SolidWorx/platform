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

namespace SolidWorx\Platform\SaasBundle\Enum;

enum FeatureType: string
{
    case BOOLEAN = 'boolean';
    case INTEGER = 'integer';
    case STRING = 'string';
    case ARRAY = 'array';
}
