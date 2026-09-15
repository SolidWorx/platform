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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Entity\User;
use SolidWorx\Platform\PlatformBundle\Model\UserInterface;
use SolidWorx\Platform\PlatformBundle\Tenant\Event\TenantCreationCheckEvent;
use SolidWorx\Platform\PlatformBundle\Tenant\Onboarding\TenantCreationGate;
use SolidWorx\Platform\PlatformBundle\Tenant\TenantLock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Ulid;

#[CoversClass(TenantCreationGate::class)]
#[CoversClass(TenantCreationCheckEvent::class)]
#[UsesClass(TenantLock::class)]
final class TenantCreationGateTest extends TestCase
{
    public function testAllowsCreationByDefault(): void
    {
        $check = $this->createGate()->check($this->user());

        self::assertTrue($check->isAllowed());
        self::assertNull($check->getReason());
    }

    public function testRefusesWhenOnboardingIsDisabled(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(TenantCreationCheckEvent::class, static function (): never {
            self::fail('Listeners must not run once the platform itself has refused.');
        });

        $check = $this->createGate($dispatcher, enabled: false)->check($this->user());

        self::assertFalse($check->isAllowed());
        self::assertSame('Creating workspaces is disabled.', $check->getReason());
    }

    /**
     * On a custom domain the workspace is fixed by the host, so a new one could not be entered.
     */
    public function testRefusesWhileTheTenantIsLocked(): void
    {
        $lock = new TenantLock();
        $lock->lock(new Ulid());

        $check = $this->createGate(lock: $lock)->check($this->user());

        self::assertFalse($check->isAllowed());
        self::assertSame('The workspace is fixed by the domain and cannot be changed.', $check->getReason());
    }

    public function testAListenerCanRefuseWithAReason(): void
    {
        $user = $this->user();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(TenantCreationCheckEvent::class, static function (TenantCreationCheckEvent $event) use ($user): void {
            self::assertSame($user, $event->getUser());

            $event->deny('Your plan is limited to one workspace.');
        });

        $check = $this->createGate($dispatcher)->check($user);

        self::assertFalse($check->isAllowed());
        self::assertSame('Your plan is limited to one workspace.', $check->getReason());
    }

    public function testTheFirstRefusalWins(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(TenantCreationCheckEvent::class, static fn (TenantCreationCheckEvent $event) => $event->deny('First.'));
        $dispatcher->addListener(TenantCreationCheckEvent::class, static fn (TenantCreationCheckEvent $event) => $event->deny('Second.'));

        self::assertSame('First.', $this->createGate($dispatcher)->check($this->user())->getReason());
    }

    private function createGate(?EventDispatcher $dispatcher = null, ?TenantLock $lock = null, bool $enabled = true): TenantCreationGate
    {
        return new TenantCreationGate($dispatcher ?? new EventDispatcher(), $lock ?? new TenantLock(), $enabled);
    }

    private function user(): UserInterface
    {
        $user = self::createStub(User::class);
        $user->method('getId')->willReturn(new Ulid());

        return $user;
    }
}
