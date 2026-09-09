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

namespace SolidWorx\Platform\DataGridBundle\DependencyInjection\CompilerPass;

use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class DataGridRegistryPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
    }
}
