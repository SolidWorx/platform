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
use Scheb\TwoFactorBundle\Security\TwoFactor\Trusted\TrustedDeviceManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reports that no device is trusted, which is the state the two-factor page is usually rendered in.
 */
final class StubTrustedDeviceManager implements TrustedDeviceManagerInterface
{
    #[Override]
    public function canSetTrustedDevice(object $user, Request $request, string $firewallName): bool
    {
        return false;
    }

    #[Override]
    public function addTrustedDevice(object $user, string $firewallName): void
    {
    }

    #[Override]
    public function isTrustedDevice(object $user, string $firewallName): bool
    {
        return false;
    }
}
