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
use Throwable;
use function array_key_exists;
use function sprintf;

final class ExtensionRequiredException extends RuntimeException
{
    private const array EXTENSION_MAP = [
        'doctrine' => 'doctrine/doctrine-bundle',
    ];

    public function __construct(string $extension, int $code = 0, ?Throwable $previous = null)
    {
        $message = sprintf('The "%s" extension is required', $extension);

        if (array_key_exists($extension, self::EXTENSION_MAP)) {
            $message .= sprintf('. Please install the "%s" package.', self::EXTENSION_MAP[$extension]);
        }

        parent::__construct($message, $code, $previous);
    }
}
