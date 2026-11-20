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
use SolidWorx\Platform\PlatformBundle\Config\PlatformConfigSectionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Records the config section the platform kernel hands to a bundle with its own section key.
 */
final class ConfigSectionSpyBundle extends Bundle implements PlatformConfigSectionInterface
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $receivedConfig = null;

    public function __construct(
        private readonly string $sectionKey,
    ) {
    }

    #[Override]
    public function getConfigSectionKey(): string
    {
        return $this->sectionKey;
    }

    #[Override]
    public function setPlatformRawConfig(array $rawConfig): void
    {
        $this->receivedConfig = $rawConfig;
    }
}
