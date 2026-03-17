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

namespace SolidWorx\Platform\PlatformBundle\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\StringType;
use League\Uri\Uri;
use Override;

final class URLType extends StringType
{
    public const string NAME = 'url';

    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Uri) {
            return $value->toString();
        }

        throw InvalidType::new($value, self::class, ['null', Uri::class]);
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Uri
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Uri) {
            return $value;
        }

        if (! is_string($value)) {
            throw InvalidType::new($value, self::class, ['null', 'string', Uri::class]);
        }

        return Uri::new($value);
    }
}
