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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Validator\Fixtures;

use SolidWorx\Platform\PlatformBundle\Model\User;

/**
 * Concrete subclass of the abstract base {@see User} so the inherited
 * validation metadata (including the disposable-email constraint) can be
 * exercised against a real instance.
 */
final class UserFixture extends User
{
}
