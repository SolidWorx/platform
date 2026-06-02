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

namespace SolidWorx\Platform\PlatformBundle\DependencyInjection\CompilerPass;

use Override;
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfigState;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Clears the compile-time {@see PlatformConfigState} once the container build is complete.
 *
 * Registered at {@see \Symfony\Component\DependencyInjection\Compiler\PassConfig::TYPE_AFTER_REMOVING}
 * so it runs after all configuration (including `config/packages/security.php`) has been
 * processed. This is housekeeping — the state is compile-time only — but it keeps the static
 * from outliving the build.
 */
final class ClearPlatformConfigStatePass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        PlatformConfigState::clear();
    }
}
