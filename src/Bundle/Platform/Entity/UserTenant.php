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
use SolidWorx\Platform\PlatformBundle\Model\UserTenant as UserTenantModel;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;

/**
 * Default concrete membership entity. Replace it by extending {@see UserTenantModel} and configuring
 * `platform.multi_tenancy.models.user_tenant` with your class.
 */
#[ORM\Entity(repositoryClass: UserTenantRepository::class)]
#[ORM\Table(name: UserTenant::TABLE_NAME)]
#[ORM\UniqueConstraint(name: 'uniq_user_tenant', fields: ['userId', 'tenant'])]
class UserTenant extends UserTenantModel
{
    final public const string TABLE_NAME = 'platform_user_tenant';
}
