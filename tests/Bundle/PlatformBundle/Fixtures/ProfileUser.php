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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures;

use SolidWorx\Platform\PlatformBundle\Model\User;

/**
 * A concrete user, standing in for whatever an application configures under
 * `platform.models.user`, so the profile form and pages can be exercised against a real instance
 * of the mapped base class.
 */
final class ProfileUser extends User
{
}
