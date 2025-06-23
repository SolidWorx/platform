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

namespace SolidWorx\Platform\PlatformBundle\Contracts\Doctrine\Repository;

use SolidWorx\Platform\PlatformBundle\Contracts\Security\TwoFactor\UserTwoFactorInterface;

/**
 * @TODO: Add a config option to specify the user class/user repository class
 * then automatically register this as an alias of the repository class in the container
 * so that it doesn't have to be done by the user
 */
interface UserRepository
{
    public function save(UserTwoFactorInterface $user): void;
}
