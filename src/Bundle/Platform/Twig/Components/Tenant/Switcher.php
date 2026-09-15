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

namespace SolidWorx\Platform\PlatformBundle\Twig\Components\Tenant;

use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantChoice;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantLock;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use function array_values;

/**
 * The workspace menu in the navigation bar: which workspace you are in, the others you can move to,
 * and the way to create another one.
 *
 * Drop it anywhere with `<twig:Platform:Tenant:Switcher />` — no surrounding condition needed. The
 * component decides for itself whether it has anything to say, and renders nothing when the tenant
 * is locked to the request (a custom domain) or the user belongs to no workspace at all. A single
 * workspace still renders: naming the one you are in is the point, even when there is nothing to
 * switch to.
 */
#[AsTwigComponent(
    name: 'Platform:Tenant:Switcher',
    template: '@Ui/components/Tenant/Switcher.html.twig',
)]
final class Switcher
{
    /**
     * @var list<TenantChoice>|null
     */
    private ?array $tenants = null;

    public function __construct(
        private readonly Security $security,
        private readonly UserTenantRepository $userTenantRepository,
        private readonly TenantContext $tenantContext,
        private readonly TenantLock $tenantLock,
    ) {
    }

    /**
     * Whether there is anything to show. A user with no workspace has no menu — they are on their
     * way to onboarding, which is a page, not a dropdown entry.
     */
    #[ExposeInTemplate]
    public function isAvailable(): bool
    {
        if ($this->tenantLock->isLocked()) {
            return false;
        }

        return $this->getTenants() !== [];
    }

    /**
     * @return list<TenantChoice>
     */
    #[ExposeInTemplate]
    public function getTenants(): array
    {
        if ($this->tenants !== null) {
            return $this->tenants;
        }

        $user = $this->security->getUser();

        if (! $user instanceof UserInterface) {
            return $this->tenants = [];
        }

        return $this->tenants = array_values($this->userTenantRepository->findTenantsForUser($user));
    }

    #[ExposeInTemplate]
    public function getCurrent(): ?TenantChoice
    {
        $tenantId = $this->tenantContext->getTenantId();

        if (! $tenantId instanceof Ulid) {
            return null;
        }

        return array_find($this->getTenants(), static fn (TenantChoice $tenant): bool => $tenant->id->equals($tenantId));
    }
}
