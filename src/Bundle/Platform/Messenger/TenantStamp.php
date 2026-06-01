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

namespace SolidWorx\Platform\PlatformBundle\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Carries the tenant in scope at dispatch time across the message bus, so the worker can restore it
 * while handling the message.
 *
 * The id is held as an RFC 4122 string for transport-serializer safety.
 */
final readonly class TenantStamp implements StampInterface
{
    private string $tenantId;

    public function __construct(Ulid $tenantId)
    {
        $this->tenantId = $tenantId->toRfc4122();
    }

    public function getTenantId(): Ulid
    {
        return Ulid::fromString($this->tenantId);
    }
}
