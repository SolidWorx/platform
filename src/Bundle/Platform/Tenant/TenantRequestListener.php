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

namespace SolidWorx\Platform\PlatformBundle\Tenant;

use SolidWorx\Platform\PlatformBundle\Tenant\Resolver\TenantResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Establishes the tenant in scope for the main request by walking the resolver chain.
 *
 * Runs after the firewall (default priority) so an authenticated user is available to the
 * access-validation listener. Only the main request is resolved; sub-requests inherit the context.
 *
 * @see TenantResolverInterface
 */
#[AsEventListener(event: KernelEvents::REQUEST)]
final readonly class TenantRequestListener
{
    /**
     * @param iterable<TenantResolverInterface> $resolvers
     */
    public function __construct(
        #[AutowireIterator('platform.tenant_resolver')]
        private iterable $resolvers,
        private TenantContext $tenantContext,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        foreach ($this->resolvers as $resolver) {
            $tenantId = $resolver->resolve($request);

            if ($tenantId !== null) {
                $this->tenantContext->setTenant($tenantId);

                return;
            }
        }
    }
}
