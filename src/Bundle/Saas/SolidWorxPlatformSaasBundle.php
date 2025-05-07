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

namespace SolidWorx\Platform\SaasBundle;

use Override;
use SolidWorx\Platform\SaasBundle\DependencyInjection\CompilerPass\ResolveTargetEntityPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SolidWorxPlatformSaasBundle extends Bundle
{
    public const string NAMESPACE = __NAMESPACE__;

    #[Override]
    public function getPath(): string
    {
        return __DIR__;
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new ResolveTargetEntityPass());
    }
}
