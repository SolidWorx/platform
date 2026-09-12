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

namespace SolidWorx\Platform\PlatformBundle\Security\EventListener;

use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Repository\TenantRepository;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Refuses authentication on a tenant's custom domain when the user is not a member of that tenant.
 *
 * Runs inside the firewall, at a low priority so credentials have already been verified. That
 * placement is the whole point: without it the user authenticates successfully and only meets a
 * {@see \SolidWorx\Platform\PlatformBundle\Exception\TenantAccessDeniedException} on the *next*
 * request, leaving a live session on a domain they can do nothing with. Failing here keeps them on
 * the login form with an explanation and establishes no session at all.
 *
 * Only active when the domain resolver and membership validation are both enabled — an application
 * that has turned membership validation off has opted out of this class of check.
 */
#[AsEventListener(event: CheckPassportEvent::class, priority: -100)]
final readonly class TenantDomainLoginListener
{
    public function __construct(
        private RequestStack $requestStack,
        private TenantRepository $tenantRepository,
        private UserTenantRepository $userTenantRepository,
    ) {
    }

    public function __invoke(CheckPassportEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (! $request instanceof Request) {
            return;
        }

        $tenant = $this->tenantRepository->findOneByDomain($request->getHost());

        if (! $tenant instanceof TenantInterface) {
            return;
        }

        $user = $event->getPassport()->getUser();

        if (! $user instanceof UserInterface) {
            return;
        }

        if ($this->userTenantRepository->hasAccess($user, $tenant)) {
            return;
        }

        throw new CustomUserMessageAuthenticationException('You do not have access to this workspace.');
    }
}
