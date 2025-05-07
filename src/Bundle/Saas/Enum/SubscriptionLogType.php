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

namespace SolidWorx\Platform\SaasBundle\Enum;

enum SubscriptionLogType: string
{
    case RENEWED = 'renewed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case PAUSED = 'paused';
    case RESUMED = 'resumed';
    case ACTIVATED = 'activated';
    case TRIAL_STARTED = 'trial_started';
    case TRIAL_ENDED = 'trial_ended';
}
