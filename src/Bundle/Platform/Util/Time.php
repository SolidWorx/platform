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

namespace SolidWorx\Platform\PlatformBundle\Util;

final readonly class Time
{
    public const int SECOND = 1;

    public const int MINUTE = self::SECOND * 60;

    public const int HOUR = self::MINUTE * 60;

    public const int DAY = self::HOUR * 24;

    public const int WEEK = self::DAY * 7;

    public const int MONTH = self::DAY * 30;

    public const int YEAR = self::DAY * 365;
}
