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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Tenant\Scope;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Entity\User;
use SolidWorx\Platform\PlatformBundle\Exception\TenantAccessDeniedException;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use SolidWorx\Platform\PlatformBundle\Tenant\Event\TenantSwitchedEvent;
use SolidWorx\Platform\PlatformBundle\Tenant\Scope\TenantScopeOutcome;
use SolidWorx\Platform\PlatformBundle\Tenant\Scope\TenantScopeResolver;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantChoice;
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

#[CoversClass(TenantScopeResolver::class)]
#[CoversClass(TenantScopeOutcome::class)]
#[UsesClass(TenantContext::class)]
#[UsesClass(TenantManager::class)]
#[UsesClass(TenantLock::class)]
#[UsesClass(TenantChoice::class)]
#[UsesClass(TenantSessionStorage::class)]
#[UsesClass(TenantSwitchedEvent::class)]
final class TenantScopeResolverTest extends TestCase
{
    private const string SESSION_KEY = '_tenant_id';

    private TenantContext $context;

    private Session $session;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->context = new TenantContext($this->eventDispatcher);
        $this->session = new Session(new MockArraySessionStorage());
    }

    public function testLeavesAnAlreadyScopedRequestAlone(): void
    {
        $this->context->setTenant(new Ulid());

        $resolver = $this->createResolver([]);

        $this->assertSame(TenantScopeOutcome::AlreadyScoped, $resolver->resolve($this->user()));
    }

    public function testEntersTheOnlyTenantAutomatically(): void
    {
        $tenantId = new Ulid();

        $resolver = $this->createResolver([new TenantChoice($tenantId, 'Acme')]);

        $this->assertSame(TenantScopeOutcome::AutoSelected, $resolver->resolve($this->user()));
        $this->assertTrue($tenantId->equals($this->context->getTenantId() ?? new Ulid()));
    }

    public function testRemembersTheAutoSelectedTenantForLaterRequests(): void
    {
        $tenantId = new Ulid();

        $this->createResolver([new TenantChoice($tenantId, 'Acme')])->resolve($this->user());

        $this->assertSame($tenantId->toRfc4122(), $this->session->get(self::SESSION_KEY));
    }

    public function testAsksForASelectionWithSeveralTenants(): void
    {
        $resolver = $this->createResolver([
            new TenantChoice(new Ulid(), 'Acme'),
            new TenantChoice(new Ulid(), 'Globex'),
        ]);

        $this->assertSame(TenantScopeOutcome::NeedsSelection, $resolver->resolve($this->user()));
        $this->assertFalse($this->context->hasTenant());
    }

    public function testOffersOnboardingWithNoTenants(): void
    {
        $resolver = $this->createResolver([]);

        $this->assertSame(TenantScopeOutcome::NeedsOnboarding, $resolver->resolve($this->user()));
    }

    public function testReportsNoAccessWhenOnboardingIsDisabled(): void
    {
        $resolver = $this->createResolver([], onboardingEnabled: false);

        $this->assertSame(TenantScopeOutcome::NoAccess, $resolver->resolve($this->user()));
    }

    /**
     * Auto-selection must not become a way around the membership check: it goes through the
     * manager, so a veto from the access-validation listener still applies.
     */
    public function testDoesNotEnterATenantTheUserWasJustRemovedFrom(): void
    {
        $this->eventDispatcher->addListener(
            TenantSwitchedEvent::class,
            static function (): void {
                throw new TenantAccessDeniedException();
            },
        );

        $resolver = $this->createResolver([new TenantChoice(new Ulid(), 'Acme')]);

        $this->assertSame(TenantScopeOutcome::NeedsOnboarding, $resolver->resolve($this->user()));
        $this->assertFalse($this->context->hasTenant());
    }

    public function testARevokedSoleTenantIsClearedFromTheSession(): void
    {
        $this->session->set(self::SESSION_KEY, (new Ulid())->toRfc4122());

        $this->eventDispatcher->addListener(
            TenantSwitchedEvent::class,
            static function (): void {
                throw new TenantAccessDeniedException();
            },
        );

        $this->createResolver([new TenantChoice(new Ulid(), 'Acme')])->resolve($this->user());

        $this->assertFalse($this->session->has(self::SESSION_KEY));
    }

    /**
     * @param list<TenantChoice> $tenants
     */
    private function createResolver(array $tenants, bool $onboardingEnabled = true): TenantScopeResolver
    {
        $repository = self::createStub(UserTenantRepository::class);
        $repository->method('findTenantsForUser')->willReturn($tenants);

        $request = new Request();
        $request->setSession($this->session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new TenantScopeResolver(
            $this->context,
            new TenantManager($this->context, self::createStub(EntityManagerInterface::class), new TenantLock()),
            new TenantSessionStorage($requestStack, self::SESSION_KEY),
            $repository,
            $onboardingEnabled,
        );
    }

    private function user(): UserInterface
    {
        $user = self::createStub(User::class);
        $user->method('getId')->willReturn(new Ulid());

        return $user;
    }
}
