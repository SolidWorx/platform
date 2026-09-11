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

namespace SolidWorx\Platform\DataGridBundle\Exception;

use InvalidArgumentException;
use function implode;
use function sprintf;

final class UnknownDataGridException extends InvalidArgumentException
{
    /**
     * @param list<string> $registered
     */
    public static function forName(string $name, array $registered): self
    {
        return new self(sprintf(
            'No data grid named "%s" is registered. Registered grids: "%s".',
            $name,
            implode('", "', $registered),
        ));
    }
}
