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

namespace SolidWorx\Platform\PlatformBundle;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Override;
use SolidWorx\Platform\PlatformBundle\Doctrine\Type\UTCDateTimeType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SolidWorxPlatformBundle extends Bundle
{
    public const string NAMESPACE = __NAMESPACE__;

    /**
     * @throws Exception
     */
    #[Override]
    public function boot(): void
    {
        if (! $this->container instanceof ContainerInterface) {
            return;
        }

        if (! $this->container->hasParameter('solidworx_platform.doctrine.types.enable_utc_date')) {
            return;
        }

        $parameter = $this->container->getParameter('solidworx_platform.doctrine.types.enable_utc_date');
        if ($parameter === true) {
            Type::overrideType(Types::DATETIMETZ_IMMUTABLE, UTCDateTimeType::class);
            Type::overrideType(Types::DATETIME_IMMUTABLE, UTCDateTimeType::class);
        }
    }

    #[Override]
    public function getPath(): string
    {
        return __DIR__;
    }
}
