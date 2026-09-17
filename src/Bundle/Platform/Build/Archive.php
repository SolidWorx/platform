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

namespace SolidWorx\Platform\PlatformBundle\Build;

/**
 * The tarball {@see AppArchiver} produced, with the metadata that goes with it.
 */
final readonly class Archive
{
    public function __construct(
        public string $path,
        public string $checksum,
        public int $fileCount,
        public int $bytes,
    ) {
    }
}
