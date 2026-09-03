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

namespace SolidWorx\Platform\PlatformBundle\Doctrine\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use SolidWorx\Platform\PlatformBundle\Entity\Tenant;
use SolidWorx\Platform\PlatformBundle\Entity\User;
use SolidWorx\Platform\PlatformBundle\Entity\UserTenant;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use function in_array;

/**
 * Disables a default platform entity when the application has overridden it via configuration.
 *
 * The platform ships ready-to-use concrete entities ({@see Tenant}, {@see UserTenant}, {@see User})
 * that map by default. When a consumer registers their own class (extending the mapped base) through
 * `platform.multi_tenancy.models.*` or `platform.models.user`, the matching default would otherwise
 * still create a duplicate table. This listener marks the superseded default as a mapped superclass
 * so it produces no table and is not treated as an entity; the configured class (wired via
 * `resolve_target_entities`) takes over.
 */
#[AsDoctrineListener(event: Events::loadClassMetadata)]
final readonly class DefaultEntityMappingListener
{
    public function __construct(
        #[Autowire(param: 'solidworx_platform.multi_tenancy.enabled')]
        private bool $multiTenancyEnabled,
        #[Autowire(param: 'solidworx_platform.multi_tenancy.models.tenant')]
        private string $tenantClass,
        #[Autowire(param: 'solidworx_platform.multi_tenancy.models.user_tenant')]
        private string $userTenantClass,
        #[Autowire(param: 'solidworx_platform.models.user')]
        private string $userClass,
    ) {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();
        $name = $metadata->getName();

        if (! in_array($name, [Tenant::class, UserTenant::class, User::class], true)) {
            return;
        }

        $isSupersededDefault = (! $this->multiTenancyEnabled || $this->isMappedModel($name)) || ($name === User::class && $this->userClass !== User::class);

        if (! $isSupersededDefault) {
            return;
        }

        $metadata->isMappedSuperclass = true;
        $metadata->isEmbeddedClass = false;
        $metadata->setCustomRepositoryClass(null);
    }

    private function isMappedModel(string $name): bool
    {
        return match ($name) {
            Tenant::class => $this->tenantClass !== Tenant::class,
            UserTenant::class => $this->userTenantClass !== UserTenant::class,
            default => false,
        };
    }
}
