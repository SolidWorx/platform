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

namespace SolidWorx\Platform\PlatformBundle\Controller\Tenant;

use SolidWorx\Platform\PlatformBundle\Response\RedirectResponse;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantSessionStorage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Works out where to send a user once they have a workspace.
 *
 * Shared by the selection page and onboarding so the two cannot drift. The order is: back to
 * whatever page the scope guard interrupted, then the configured default route, then the site root.
 */
final readonly class TenantRedirector
{
    public function __construct(
        private TenantSessionStorage $sessionStorage,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'solidworx_platform.multi_tenancy.default_route')]
        private ?string $defaultRoute,
    ) {
    }

    public function redirectAfterSelection(): RedirectResponse
    {
        $target = $this->sessionStorage->consumeTargetPath();

        if ($target !== null) {
            return new RedirectResponse($target);
        }

        if ($this->defaultRoute !== null) {
            return new RedirectResponse($this->urlGenerator->generate($this->defaultRoute));
        }

        return new RedirectResponse('/');
    }
}
