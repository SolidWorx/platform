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

namespace SolidWorx\Platform\PlatformBundle\Tenant\Onboarding;

use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Tenant\Event\TenantCreationCheckEvent;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantLock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The single answer to "may this user create a workspace?".
 *
 * Everything that offers creation asks here first — the switcher and the selection page to decide
 * whether to show the link, the onboarding controller to decide whether to serve the form — so a
 * refusal cannot be routed around by going straight to the URL.
 *
 * Two refusals are built in: onboarding turned off in the configuration, and a request pinned to a
 * tenant by its domain, where there is nothing to switch into afterwards. Anything else is an
 * application concern, and belongs in a {@see TenantCreationCheckEvent} listener.
 */
final readonly class TenantCreationGate
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private TenantLock $tenantLock,
        #[Autowire(param: 'solidworx_platform.multi_tenancy.onboarding.enabled')]
        private bool $enabled,
    ) {
    }

    /**
     * Returns the decision rather than a bool, since a refusal carries the reason to show the user.
     * The event is not dispatched when the platform itself refuses — listeners exist to add limits,
     * not to lift them.
     */
    public function check(UserInterface $user): TenantCreationCheckEvent
    {
        $event = new TenantCreationCheckEvent($user);

        if (! $this->enabled) {
            $event->deny('Creating workspaces is disabled.');

            return $event;
        }

        if ($this->tenantLock->isLocked()) {
            $event->deny('The workspace is fixed by the domain and cannot be changed.');

            return $event;
        }

        $this->eventDispatcher->dispatch($event);

        return $event;
    }
}
