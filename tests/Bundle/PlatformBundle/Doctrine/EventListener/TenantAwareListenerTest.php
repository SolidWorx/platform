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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Doctrine\EventListener;

use Symfony\Component\Uid\Ulid;
use Doctrine\Common\EventManager;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\CoversClass;
use SolidWorx\Platform\PlatformBundle\Doctrine\EventListener\TenantAwareListener;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Entity\TenantAwareItem;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\TenantOrmTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(TenantAwareListener::class)]
final class TenantAwareListenerTest extends TenantOrmTestCase
{
    public function testAutoSetsTenantOnPersist(): void
    {
        $context = new TenantContext(new EventDispatcher());
        $tenantId = $this->ulid();
        $context->setTenant($tenantId);

        $eventManager = new EventManager();
        $eventManager->addEventListener([Events::prePersist], new TenantAwareListener($context));

        $entityManager = $this->createTenantEntityManager($eventManager);

        $item = new TenantAwareItem('item', $this->ulid());
        $entityManager->persist($item);
        $entityManager->flush();

        $this->assertSame($tenantId->toRfc4122(), $item->getTenantId()?->toRfc4122());
    }

    public function testDoesNothingWithoutTenantInScope(): void
    {
        $context = new TenantContext(new EventDispatcher());

        $eventManager = new EventManager();
        $eventManager->addEventListener([Events::prePersist], new TenantAwareListener($context));

        $entityManager = $this->createTenantEntityManager($eventManager);

        $item = new TenantAwareItem('item', $this->ulid());
        $entityManager->persist($item);
        $entityManager->flush();

        $this->assertNotInstanceOf(Ulid::class, $item->getTenantId());
    }
}
