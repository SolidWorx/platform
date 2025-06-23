<?php

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
