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
use function count;

/**
 * Renders the list of workspaces the current user can switch between.
 *
 * Drop it anywhere with `<twig:Platform:Tenant:Switcher />` — no surrounding condition needed. The
 * component decides for itself whether it has anything to say, and renders nothing when the tenant
 * is locked to the request (a custom domain) or the user only belongs to one workspace, since
 * neither case offers a choice.
 */
#[AsTwigComponent(
    name: 'Platform:Tenant:Switcher',
    template: '@Ui/components/Tenant/Switcher.html.twig',
)]
final class Switcher
{
    /**
     * A CSS class applied to each entry, so the component can sit in a dropdown, a sidebar or a
     * standalone card without being restyled.
     */
    public string $itemClass = 'dropdown-item';

    /**
     * Whether to close the list with a separator, for when it sits above other menu entries.
     */
    public bool $divider = true;

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
     * Whether there is a choice worth offering.
     */
    #[ExposeInTemplate]
    public function isAvailable(): bool
    {
        if ($this->tenantLock->isLocked()) {
            return false;
        }

        return count($this->getTenants()) > 1;
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

        foreach ($this->getTenants() as $tenant) {
            if ($tenant->id->equals($tenantId)) {
                return $tenant;
            }
        }

        return null;
    }
}
