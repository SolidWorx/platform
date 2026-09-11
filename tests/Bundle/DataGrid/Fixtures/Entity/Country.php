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

namespace SolidWorx\Platform\Tests\Bundle\DataGrid\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Stringable;

#[ORM\Entity]
class Country implements Stringable
{
    #[ORM\Id]
    #[ORM\Column]
    private int $id = 0;

    #[ORM\Column]
    private string $name = '';

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->name;
    }
}
