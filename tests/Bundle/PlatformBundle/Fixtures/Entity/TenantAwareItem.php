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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantAwareInterface;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantAwareTrait;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * Minimal tenant-aware entity used by the multi-tenancy functional tests.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tenant_aware_item')]
#[ORM\Index(columns: ['name', 'tenant_id'], name: 'idx_item_name_tenant')]
class TenantAwareItem implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME)]
    private Ulid $id;

    public function __construct(#[ORM\Column(type: Types::STRING)]
    private string $name, ?Ulid $id = null)
    {
        $this->id = $id ?? new Ulid();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
