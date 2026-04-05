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

namespace SolidWorx\Platform\SaasBundle\Trial;

use Symfony\Component\Uid\Ulid;

interface TrialUserInterface
{
    /**
     * Returns the persisted ULID for this user.
     * Implementations must be persisted entities with a real ULID before being passed to trial operations.
     */
    public function getId(): ?Ulid;
}
