<?php

namespace SolidWorx\Platform\PlatformBundle\DependencyInjection;

final class Util
{
    public const string PREFIX = 'solidworx_platform';

    public static function tag(string $name): string
    {
        return self::PREFIX . '.' . $name;
    }
}
