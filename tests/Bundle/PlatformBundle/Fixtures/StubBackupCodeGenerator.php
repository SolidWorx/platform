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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures;

use Override;
use SolidWorx\Platform\PlatformBundle\Security\TwoFactor\BackupCodeGeneratorInterface;
use function array_map;
use function range;
use function sprintf;

/**
 * Predictable backup codes, so a test can name the ones it expects to see.
 */
final class StubBackupCodeGenerator implements BackupCodeGeneratorInterface
{
    #[Override]
    public function generateCode(): string
    {
        return 'AAAA-0000';
    }

    #[Override]
    public function generateBackupCodes(int $limit = self::LIMIT): array
    {
        return array_map(static fn (int $i): string => sprintf('CODE-%04d', $i), range(1, $limit));
    }
}
