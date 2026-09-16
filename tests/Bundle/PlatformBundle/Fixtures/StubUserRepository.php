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
use SolidWorx\Platform\PlatformBundle\Contracts\Doctrine\Repository\UserRepository;
use SolidWorx\Platform\PlatformBundle\Contracts\Security\TwoFactor\UserTwoFactorInterface;

/**
 * The collaborators the two-factor component needs in order to be mounted, with the persistence
 * and the cryptography taken out.
 *
 * Together with {@see StubTotpAuthenticator} they are what lets the component be rendered as a real
 * live component without a database or a scheb configuration.
 */
final class StubUserRepository implements UserRepository
{
    #[Override]
    public function save(UserTwoFactorInterface $user): void
    {
    }
}
