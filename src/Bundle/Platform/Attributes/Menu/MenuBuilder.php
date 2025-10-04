<?php

namespace SolidWorx\Platform\PlatformBundle\Attributes\Menu;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class MenuBuilder
{
    public function __construct(
        public readonly string $name,
        public readonly int $priority = 0,
        public readonly string $role = '',
    ) {
    }
}
