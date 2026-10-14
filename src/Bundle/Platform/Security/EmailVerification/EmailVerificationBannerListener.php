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

namespace SolidWorx\Platform\PlatformBundle\Security\EmailVerification;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Twig\Environment;
use function preg_replace;
use function str_contains;

/**
 * Puts the "verify your email" banner at the top of every page an unverified user sees.
 *
 * The banner is injected into the response rather than included by a layout, so it also reaches
 * pages that replace the whole response — a subscription paywall, for instance — and the user can
 * always ask for a new link. It is rendered from `platform.security.email_verification.banner_template`.
 */
final readonly class EmailVerificationBannerListener
{
    private const string ANCHOR = '<div class="page-wrapper">';

    public function __construct(
        private EmailVerificationGateInterface $gate,
        private Environment $twig,
        #[Autowire(param: 'solidworx_platform.security.email_verification.banner_template')]
        private string $template,
    ) {
    }

    // Priority -10 runs after other banner listeners at the default priority, so this one lands
    // on top of them.
    #[AsEventListener(priority: -10)]
    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        if (! $event->isMainRequest() || ! $event->getRequest()->isMethod('GET') || $response->getStatusCode() !== Response::HTTP_OK) {
            return;
        }

        $content = $response->getContent();
        if ($content === false || ! str_contains($content, self::ANCHOR) || ! $this->gate->isGated()) {
            return;
        }

        $banner = $this->twig->render($this->template);

        $response->setContent(preg_replace('/' . preg_quote(self::ANCHOR, '/') . '/', self::ANCHOR . $banner, $content, 1));
    }
}
