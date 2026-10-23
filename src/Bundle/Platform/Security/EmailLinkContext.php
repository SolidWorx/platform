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

namespace SolidWorx\Platform\PlatformBundle\Security;

use InvalidArgumentException;
use SolidWorx\Platform\PlatformBundle\Exception\UntrustedLinkHostException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use function preg_match;
use function sprintf;

/**
 * Builds the absolute links the platform emails — password reset and email verification — on a
 * host an attacker cannot choose.
 *
 * Left alone, the router takes the host of the current request. With nothing checking that host,
 * anyone could trigger an email to a victim with a `Host` header of their own, and the victim
 * would receive a genuine link pointing at the attacker's site. So either:
 *
 * - `platform.security.base_url` (else `framework.router.default_uri`) is set, and the router
 *   builds the link on it; or
 * - `framework.trusted_hosts` is set, so Symfony has already rejected any other host — which
 *   keeps per-tenant custom domains working.
 *
 * With neither, {@see UntrustedLinkHostException} is thrown rather than a link sent. Debug mode is
 * exempt so a local setup works as is, and so is code running outside a request (a console
 * command, a worker), where the router has no header to take a host from.
 */
final readonly class EmailLinkContext
{
    public function __construct(
        private RouterInterface $router,
        private RequestStack $requestStack,
        // `platform.security.base_url`, else `framework.router.default_uri`; '' when neither is set.
        #[Autowire(param: 'solidworx_platform.security.base_url')]
        private ?string $baseUrl = null,
        #[Autowire(param: 'kernel.debug')]
        private bool $debug = false,
    ) {
    }

    /**
     * Runs `$build` with the router pointed at the configured base URL, if there is one.
     *
     * A callback rather than a URL in and out, because a signed link (the verification email's) is
     * signed over its host: it has to be generated on the right host, not rewritten afterwards.
     *
     * @template T
     *
     * @param callable(): T $build
     *
     * @return T
     *
     * @throws UntrustedLinkHostException
     */
    public function build(callable $build): mixed
    {
        $baseUrl = $this->baseUrl();

        if ($baseUrl === null) {
            $this->assertRequestHostIsTrusted();

            return $build();
        }

        $original = $this->router->getContext();

        $context = RequestContext::fromUri($baseUrl);
        $context->setParameters($original->getParameters());

        $this->router->setContext($context);

        try {
            return $build();
        } finally {
            $this->router->setContext($original);
        }
    }

    /**
     * @throws UntrustedLinkHostException
     */
    public function assertRequestHostIsTrusted(): void
    {
        if ($this->baseUrl() !== null || $this->debug || ! $this->requestStack->getCurrentRequest() instanceof Request || Request::getTrustedHosts() !== []) {
            return;
        }

        throw new UntrustedLinkHostException();
    }

    public static function isValidBaseUrl(string $url): bool
    {
        return preg_match('#^https?://[^/]+#', $url) === 1;
    }

    /**
     * Checked here rather than in the configuration, because an %env()% value is only known now.
     */
    private function baseUrl(): ?string
    {
        if ($this->baseUrl === null || $this->baseUrl === '') {
            return null;
        }

        if (! self::isValidBaseUrl($this->baseUrl)) {
            throw new InvalidArgumentException(sprintf('The base URL for emailed links must start with http:// or https:// and a host, got "%s". Check "platform.security.base_url" or "framework.router.default_uri".', $this->baseUrl));
        }

        return $this->baseUrl;
    }
}
