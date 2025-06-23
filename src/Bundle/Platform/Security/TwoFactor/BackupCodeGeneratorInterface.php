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

namespace SolidWorx\Platform\PlatformBundle\Security\TwoFactor;

interface BackupCodeGeneratorInterface
{
    public const int LIMIT = 8;

    public function generateCode(): string;

    /**
     * @return list<string>
     */
    public function generateBackupCodes(int $limit = self::LIMIT): array;
}
