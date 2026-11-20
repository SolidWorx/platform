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

namespace SolidWorx\Platform\PlatformBundle\Kms;

use Composer\InstalledVersions;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;
use function class_exists;
use function version_compare;

final class KmsSupport
{
    /**
     * Whether encrypted columns can work here: the Doctrine ORM bridge is installed, on top of
     * doctrine/orm ^3 and doctrine/dbal ^4.3 (what the bridge needs).
     *
     * Compared with version_compare() rather than InstalledVersions::satisfies(), which needs
     * composer/semver, a package an application does not necessarily have.
     */
    public static function isAvailable(): bool
    {
        return class_exists(Encrypted::class)
            && self::inRange('doctrine/orm', '3.0.0', '4.0.0')
            && self::inRange('doctrine/dbal', '4.3.0', '5.0.0');
    }

    private static function inRange(string $package, string $min, string $max): bool
    {
        $version = InstalledVersions::getVersion($package);

        return $version !== null
            && version_compare($version, $min, '>=')
            && version_compare($version, $max, '<');
    }
}
