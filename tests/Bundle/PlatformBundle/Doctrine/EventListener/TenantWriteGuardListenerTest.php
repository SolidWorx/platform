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

use Doctrine\Common\EventManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\CoversClass;
use SolidWorx\Platform\PlatformBundle\Doctrine\EventListener\TenantWriteGuardListener;
use SolidWorx\Platform\PlatformBundle\Exception\CrossTenantOperationException;
use SolidWorx\Platform\PlatformBundle\Exception\TenantAccessDeniedException;
use SolidWorx\Platform\PlatformBundle\Model\User;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\Entity\TenantAwareItem;
use SolidWorx\Platform\Tests\Bundle\PlatformBundle\Fixtures\TenantOrmTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Ulid;

#[CoversClass(TenantWriteGuardListener::class)]
#[CoversClass(CrossTenantOperationException::class)]
final class TenantWriteGuardListenerTest extends TenantOrmTestCase
{
    public function testThrowsOnCrossTenantWrite(): void
    {
        $tenantA = $this->ulid();
        $tenantB = $this->ulid();

        $eventManager = new EventManager();
        $entityManager = $this->createTenantEntityManager($eventManager);
        $this->registerGuard($eventManager, $entityManager, $tenantA, false);

        $item = new TenantAwareItem('item', $this->ulid());
        $item->setTenantId($tenantB);

        $entityManager->persist($item);

        $this->expectException(CrossTenantOperationException::class);

        $entityManager->flush();
    }

    public function testAllowsSameTenantWrite(): void
    {
        $tenantA = $this->ulid();

        $eventManager = new EventManager();
        $entityManager = $this->createTenantEntityManager($eventManager);
        $this->registerGuard($eventManager, $entityManager, $tenantA, false);

        $item = new TenantAwareItem('item', $this->ulid());
        $item->setTenantId($tenantA);

        $entityManager->persist($item);
        $entityManager->flush();

        $this->assertSame($tenantA->toRfc4122(), $item->getTenantId()?->toRfc4122());
    }

    public function testDeniesWhenUserIsNotMember(): void
    {
        $tenantA = $this->ulid();

        $user = self::createStub(User::class);
        $user->method('getId')->willReturn($this->ulid());

        $security = self::createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $repository = self::createStub(UserTenantRepository::class);
        $repository->method('hasAccess')->willReturn(false);

        $eventManager = new EventManager();
        $entityManager = $this->createTenantEntityManager($eventManager);

        $context = new TenantContext(new EventDispatcher());
        $context->setTenant($tenantA);

        $eventManager->addEventListener(
            [Events::onFlush],
            new TenantWriteGuardListener($context, $entityManager, $security, $repository, true),
        );

        $item = new TenantAwareItem('item', $this->ulid());
        $item->setTenantId($tenantA);

        $entityManager->persist($item);

        $this->expectException(TenantAccessDeniedException::class);

        $entityManager->flush();
    }

    private function registerGuard(
        EventManager $eventManager,
        EntityManagerInterface $entityManager,
        Ulid $tenantId,
        bool $checkUserAccess,
    ): void {
        $context = new TenantContext(new EventDispatcher());
        $context->setTenant($tenantId);

        $security = self::createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $repository = self::createStub(UserTenantRepository::class);

        $eventManager->addEventListener(
            [Events::onFlush],
            new TenantWriteGuardListener($context, $entityManager, $security, $repository, $checkUserAccess),
        );
    }
}
