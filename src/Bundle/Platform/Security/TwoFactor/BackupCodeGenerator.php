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

use Override;
use function bin2hex;
use function random_bytes;
use function strtoupper;

final class BackupCodeGenerator implements BackupCodeGeneratorInterface
{
    #[Override]
    public function generateCode(): string
    {
        return strtoupper(bin2hex(random_bytes(3)) . '-' . bin2hex(random_bytes(3)));
    }

    #[Override]
    public function generateBackupCodes(int $limit = self::LIMIT): array
    {
        $codes = [];

        for ($i = 0; $i < $limit; $i++) {
            $codes[] = $this->generateCode();
        }

        return $codes;
    }
}
