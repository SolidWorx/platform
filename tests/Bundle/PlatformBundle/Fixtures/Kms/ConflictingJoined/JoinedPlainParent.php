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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Kms\ConflictingJoined;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;

/**
 * Not tenant-aware, but its tenant-aware child stores the inherited column in this table, under this table's scope.
 */
#[ORM\Entity]
#[ORM\Table(name: 'joined_parent')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string')]
#[ORM\DiscriminatorMap([
    'parent' => JoinedPlainParent::class,
    'child' => JoinedTenantChild::class,
])]
class JoinedPlainParent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column]
    #[Encrypted]
    public string $code = '';
}
