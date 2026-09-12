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

namespace SolidWorx\Platform\PlatformBundle\Tenant;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Uid\Ulid;
use function is_string;

/**
 * Reads and writes the selected tenant, and the path to return to, in the session.
 *
 * The session is the tenant's home between requests on the primary domain. Centralising access here
 * keeps the key name in one place — the resolver, the scope guard, the selection page and
 * onboarding all go through it — and means a request without a session (a console command, a
 * stateless API firewall) degrades to a no-op rather than an error.
 */
final readonly class TenantSessionStorage
{
    /**
     * Where the scope guard remembers the page it interrupted, so selection and onboarding can
     * return the user to it.
     */
    private const string TARGET_PATH_KEY = '_tenant_scope_target';

    public function __construct(
        private RequestStack $requestStack,
        #[Autowire(param: 'solidworx_platform.multi_tenancy.session_key')]
        private string $sessionKey,
    ) {
    }

    public function getTenantId(): ?Ulid
    {
        $value = $this->session()?->get($this->sessionKey);

        if (! is_string($value)) {
            return null;
        }

        try {
            return Ulid::fromString($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function setTenantId(Ulid $tenantId): void
    {
        $this->session()?->set($this->sessionKey, $tenantId->toRfc4122());
    }

    public function clearTenantId(): void
    {
        $this->session()?->remove($this->sessionKey);
    }

    /**
     * Remembers a path to return to after the user has picked or created a tenant.
     *
     * Only ever a path — never a full URL — so the stored value cannot be turned into an
     * open redirect.
     */
    public function setTargetPath(string $path): void
    {
        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return;
        }

        $this->session()?->set(self::TARGET_PATH_KEY, $path);
    }

    /**
     * Returns the remembered path and forgets it, so a stale target cannot resurface later.
     */
    public function consumeTargetPath(): ?string
    {
        $session = $this->session();

        if (! $session instanceof SessionInterface) {
            return null;
        }

        $path = $session->get(self::TARGET_PATH_KEY);
        $session->remove(self::TARGET_PATH_KEY);

        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        return $path;
    }

    private function session(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();

        if (! $request instanceof Request || ! $request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }
}
