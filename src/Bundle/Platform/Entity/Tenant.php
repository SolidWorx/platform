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
use SolidWorx\Platform\PlatformBundle\Model\Tenant as TenantModel;
use SolidWorx\Platform\PlatformBundle\Repository\TenantRepository;

/**
 * Default concrete tenant entity. Replace it by extending {@see TenantModel} and configuring
 * `platform.multi_tenancy.models.tenant` with your class.
 */
#[ORM\Entity(repositoryClass: TenantRepository::class)]
#[ORM\Table(name: Tenant::TABLE_NAME)]
class Tenant extends TenantModel
{
    final public const string TABLE_NAME = 'platform_tenant';
}
