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

namespace SolidWorx\Platform\PlatformBundle\DependencyInjection;

final class Util
{
    public const string PREFIX = 'solidworx_platform';

    public static function tag(string $name): string
    {
        return self::PREFIX . '.' . $name;
    }
}
