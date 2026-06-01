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

namespace SolidWorx\Platform\PlatformBundle\Tenant\Resolver;

use SolidWorx\Platform\PlatformBundle\Repository\TenantRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Ulid;

/**
 * Resolves the tenant from the request host, by matching it against {@see Tenant::$domain}.
 *
 * Highest priority in the chain: a custom domain is an unambiguous, infrastructure-level signal and
 * must win over session or route hints.
 */
#[AutoconfigureTag('platform.tenant_resolver', ['priority' => 30])]
final readonly class DomainTenantResolver implements TenantResolverInterface
{
    public function __construct(
        private TenantRepository $tenantRepository,
    ) {
    }

    public function resolve(Request $request): ?Ulid
    {
        return $this->tenantRepository->findOneByDomain($request->getHost())?->getId();
    }
}
