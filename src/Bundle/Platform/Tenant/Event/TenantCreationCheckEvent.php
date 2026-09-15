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

namespace SolidWorx\Platform\PlatformBundle\Tenant\Event;

use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Asks whether a user may create another workspace, before the onboarding page is offered or served.
 *
 * Listeners can only say no, and have to say why — the reason is what the user is shown. Use it to
 * cap the number of workspaces, gate creation behind a plan, or restrict it to invited users:
 *
 * ```php
 * #[AsEventListener]
 * public function __invoke(TenantCreationCheckEvent $event): void
 * {
 *     if ($this->tenants->countForUser($event->getUser()) >= 3) {
 *         $event->deny('Your plan is limited to three workspaces.');
 *     }
 * }
 * ```
 *
 * Dispatched by {@see \SolidWorx\Platform\PlatformBundle\Tenant\Onboarding\TenantCreationGate}, which
 * every place that offers creation goes through, so a listener covers the switcher, the selection
 * page and the onboarding route at once.
 */
final class TenantCreationCheckEvent extends Event
{
    private ?string $reason = null;

    public function __construct(
        private readonly UserInterface $user,
    ) {
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function isAllowed(): bool
    {
        return $this->reason === null;
    }

    /**
     * Refuses creation, with a reason shown to the user. The first refusal wins — a later listener
     * cannot overwrite it, and none can allow what another has already denied.
     */
    public function deny(string $reason): void
    {
        $this->reason ??= $reason;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
