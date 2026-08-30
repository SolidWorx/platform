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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Security\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Entity\Tenant;
use SolidWorx\Platform\PlatformBundle\Entity\User;
use SolidWorx\Platform\PlatformBundle\Model\TenantInterface;
use SolidWorx\Platform\PlatformBundle\Repository\TenantRepository;
use SolidWorx\Platform\PlatformBundle\Repository\UserTenantRepository;
use SolidWorx\Platform\PlatformBundle\Security\EventListener\TenantDomainLoginListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Uid\Ulid;

#[CoversClass(TenantDomainLoginListener::class)]
#[UsesClass(\SolidWorx\Platform\PlatformBundle\Model\Tenant::class)]
final class TenantDomainLoginListenerTest extends TestCase
{
    /**
     * The whole point of checking here rather than on the next request: no session is established
     * for a user who cannot use the workspace this domain belongs to.
     */
    public function testRejectsLoginOnACustomDomainWithoutMembership(): void
    {
        $listener = $this->createListener(new Tenant('Acme'), hasAccess: false);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessageIsOrContains('You do not have access to this workspace.');

        $listener($this->createEvent());
    }

    public function testAllowsLoginOnACustomDomainWithMembership(): void
    {
        $listener = $this->createListener(new Tenant('Acme'), hasAccess: true);

        $listener($this->createEvent());

        $this->expectNotToPerformAssertions();
    }

    public function testIgnoresARequestThatIsNotOnACustomDomain(): void
    {
        $listener = $this->createListener(null, hasAccess: false);

        $listener($this->createEvent());

        $this->expectNotToPerformAssertions();
    }

    public function testIgnoresAnEmptyRequestStack(): void
    {
        $listener = new TenantDomainLoginListener(
            new RequestStack(),
            self::createStub(TenantRepository::class),
            self::createStub(UserTenantRepository::class),
        );

        $listener($this->createEvent());

        $this->expectNotToPerformAssertions();
    }

    private function createListener(?TenantInterface $tenant, bool $hasAccess): TenantDomainLoginListener
    {
        $tenantRepository = self::createStub(TenantRepository::class);
        $tenantRepository->method('findOneByDomain')->willReturn($tenant);

        $userTenantRepository = self::createStub(UserTenantRepository::class);
        $userTenantRepository->method('hasAccess')->willReturn($hasAccess);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://acme.example.com/login'));

        return new TenantDomainLoginListener($requestStack, $tenantRepository, $userTenantRepository);
    }

    private function createEvent(): CheckPassportEvent
    {
        $user = self::createStub(User::class);
        $user->method('getId')->willReturn(new Ulid());

        $passport = new Passport(
            new UserBadge('user@example.com', static fn (): UserInterface => $user),
            new CustomCredentials(static fn (): bool => true, 'secret'),
        );

        return new CheckPassportEvent(
            self::createStub(\Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface::class),
            $passport,
        );
    }
}
