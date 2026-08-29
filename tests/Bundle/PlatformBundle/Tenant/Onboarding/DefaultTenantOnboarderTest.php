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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Tenant\Onboarding;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Entity\Tenant;
use SolidWorx\Platform\PlatformBundle\Entity\User;
use SolidWorx\Platform\PlatformBundle\Entity\UserTenant;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Model\UserTenantInterface;
use SolidWorx\Platform\PlatformBundle\Tenant\Event\TenantCreatedEvent;
use SolidWorx\Platform\PlatformBundle\Tenant\Event\TenantSwitchedEvent;
use SolidWorx\Platform\PlatformBundle\Tenant\Onboarding\DefaultTenantOnboarder;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantContext;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantLock;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantManager;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantSessionStorage;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Uid\Ulid;

#[CoversClass(DefaultTenantOnboarder::class)]
#[CoversClass(TenantCreatedEvent::class)]
#[UsesClass(TenantContext::class)]
#[UsesClass(TenantManager::class)]
#[UsesClass(TenantLock::class)]
#[UsesClass(TenantSessionStorage::class)]
#[UsesClass(TenantSwitchedEvent::class)]
#[UsesClass(\SolidWorx\Platform\PlatformBundle\Model\Tenant::class)]
#[UsesClass(\SolidWorx\Platform\PlatformBundle\Model\UserTenant::class)]
final class DefaultTenantOnboarderTest extends TestCase
{
    private TenantContext $context;

    private Session $session;

    private EventDispatcher $eventDispatcher;

    /**
     * @var list<object>
     */
    private array $persisted = [];

    private bool $flushed = false;

    protected function setUp(): void
    {
        $this->context = new TenantContext(new EventDispatcher());
        $this->eventDispatcher = new EventDispatcher();
        $this->session = new Session(new MockArraySessionStorage());
        $this->persisted = [];
        $this->flushed = false;
    }

    public function testPersistsTheTenantAndTheCreatorsMembership(): void
    {
        $tenant = new Tenant('Acme');
        $user = $this->user();

        $this->createOnboarder()->onboard($tenant, $user);

        $this->assertContains($tenant, $this->persisted);
        $this->assertTrue($this->flushed);

        $memberships = array_values(array_filter(
            $this->persisted,
            static fn (object $entity): bool => $entity instanceof UserTenantInterface,
        ));

        $this->assertCount(1, $memberships);
        $this->assertSame($user, $memberships[0]->getUser());
        $this->assertSame($tenant, $memberships[0]->getTenant());
    }

    public function testRecordsWhoCreatedTheTenant(): void
    {
        $tenant = new Tenant('Acme');
        $user = $this->user();

        $this->createOnboarder()->onboard($tenant, $user);

        $this->assertSame($user->getId(), $tenant->getCreatedById());
    }

    public function testEntersTheNewTenant(): void
    {
        $tenant = new Tenant('Acme');

        $this->createOnboarder()->onboard($tenant, $this->user());

        $this->assertTrue($tenant->getId()->equals($this->context->getTenantId() ?? new Ulid()));
        $this->assertSame($tenant->getId()->toRfc4122(), $this->session->get('_tenant_id'));
    }

    /**
     * Listeners seed a new workspace, so they must run with the tenant already in scope — otherwise
     * anything tenant-aware they create would be unattributed.
     */
    public function testAnnouncesTheNewTenantWithItAlreadyInScope(): void
    {
        $tenant = new Tenant('Acme');
        $user = $this->user();

        $seen = null;
        $scopedDuringEvent = false;

        $this->eventDispatcher->addListener(
            TenantCreatedEvent::class,
            function (TenantCreatedEvent $event) use (&$seen, &$scopedDuringEvent): void {
                $seen = $event;
                $scopedDuringEvent = $this->context->hasTenant();
            },
        );

        $this->createOnboarder()->onboard($tenant, $user);

        $this->assertInstanceOf(TenantCreatedEvent::class, $seen);
        $this->assertSame($tenant, $seen->getTenant());
        $this->assertSame($user, $seen->getCreator());
        $this->assertTrue($scopedDuringEvent);
    }

    private function createOnboarder(): DefaultTenantOnboarder
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $entityManager->method('flush')->willReturnCallback(function (): void {
            $this->flushed = true;
        });

        $request = new Request();
        $request->setSession($this->session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new DefaultTenantOnboarder(
            $entityManager,
            new TenantManager($this->context, self::createStub(EntityManagerInterface::class), new TenantLock()),
            new TenantSessionStorage($requestStack, '_tenant_id'),
            $this->eventDispatcher,
            UserTenant::class,
        );
    }

    private function user(): UserInterface
    {
        $user = self::createStub(User::class);
        $user->method('getId')->willReturn(new Ulid());

        return $user;
    }
}
