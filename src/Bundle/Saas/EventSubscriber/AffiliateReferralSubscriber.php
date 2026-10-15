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

namespace SolidWorx\Platform\SaasBundle\EventSubscriber;

use Override;
use SolidWorx\Platform\SaasBundle\Integration\LemonSqueezyAffiliate;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps the affiliate click id from `?aff_ref=` in a first-party cookie so it
 * survives sign-up until the subscription is created.
 */
final readonly class AffiliateReferralSubscriber implements EventSubscriberInterface
{
    // ponytail: fixed lifetime; Lemon Squeezy enforces the real attribution window when the click id is used.
    private const string COOKIE_LIFETIME = '+90 days';

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $ref = $event->getRequest()->query->get(LemonSqueezyAffiliate::QUERY_PARAMETER);

        if (! LemonSqueezyAffiliate::isValid($ref)) {
            return;
        }

        $event->getResponse()->headers->setCookie(
            Cookie::create(LemonSqueezyAffiliate::COOKIE, $ref, self::COOKIE_LIFETIME)
        );
    }
}
