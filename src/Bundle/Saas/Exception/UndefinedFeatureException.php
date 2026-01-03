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

namespace SolidWorx\Platform\SaasBundle\Exception;

use RuntimeException;
use function sprintf;

final class UndefinedFeatureException extends RuntimeException
{
    public function __construct(string $featureKey, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf(
            'Feature "%s" is not defined. Make sure it is configured in solid_worx_platform_saas.features.',
            $featureKey
        ), $code, $previous);
    }
}
