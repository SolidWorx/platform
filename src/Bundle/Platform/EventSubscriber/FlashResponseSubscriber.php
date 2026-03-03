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

namespace SolidWorx\Platform\PlatformBundle\EventSubscriber;

use Override;
use SolidWorx\Platform\PlatformBundle\Response\RedirectResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

final class FlashResponseSubscriber implements EventSubscriberInterface
{
    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            ResponseEvent::class => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        if (! $response instanceof RedirectResponse) {
            return;
        }

        $flashes = $response->getFlashes();

        if ($flashes === []) {
            return;
        }

        $session = $event->getRequest()->getSession();

        if (! $session instanceof FlashBagAwareSessionInterface) {
            return;
        }

        foreach ($flashes as $flash) {
            $session->getFlashBag()->add($flash['type']->value, $flash['message']);
        }
    }
}
