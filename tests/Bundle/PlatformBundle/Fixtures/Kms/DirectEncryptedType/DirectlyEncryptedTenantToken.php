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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Kms\DirectEncryptedType;

use Doctrine\ORM\Mapping as ORM;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantAwareInterface;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantAwareTrait;

/**
 * Tenant-aware, but mapped straight to a configured encrypted type instead of #[Encrypted].
 */
#[ORM\Entity]
#[ORM\Table(name: 'directly_encrypted_tenant_token')]
class DirectlyEncryptedTenantToken implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(type: 'app_tenant_token')]
    public string $token = '';
}
