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

namespace SolidWorx\Platform\PlatformBundle\Model;

use SolidWorx\Platform\PlatformBundle\Contracts\Security\TwoFactor\UserTwoFactorInterface;
use Stringable;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface as SecurityUserInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Contract for the user entity.
 *
 * Extend {@see User} (the mapped base) to add your own fields and register the concrete class via
 * `platform.models.user`. Associations target this interface and are wired with Doctrine
 * `resolve_target_entities`.
 *
 * Identifiers are ULIDs across every platform entity; see {@see TenantInterface} and
 * {@see UserTenantInterface} for the sibling contracts.
 */
interface UserInterface extends SecurityUserInterface, PasswordAuthenticatedUserInterface, Stringable, UserTwoFactorInterface
{
    public function getId(): Ulid;
}
