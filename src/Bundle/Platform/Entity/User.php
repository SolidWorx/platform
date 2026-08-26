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

namespace SolidWorx\Platform\PlatformBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use SolidWorx\Platform\PlatformBundle\Model\User as UserModel;
use SolidWorx\Platform\PlatformBundle\Repository\UserRepository;

/**
 * Default concrete user entity. Replace it by extending {@see UserModel} and configuring
 * `platform.models.user` with your class.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: User::TABLE_NAME)]
class User extends UserModel
{
    final public const string TABLE_NAME = 'users';
}
