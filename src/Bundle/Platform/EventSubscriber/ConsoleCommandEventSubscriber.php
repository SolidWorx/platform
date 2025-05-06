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
use SolidWorx\Platform\PlatformBundle\Console\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ConsoleCommandEventSubscriber implements EventSubscriberInterface
{
    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleCommandEvent::class => 'onConsoleEvent',
        ];
    }

    public function onConsoleEvent(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();

        if (! $command instanceof Command) {
            return;
        }

        $input = $event->getInput();
        $output = $event->getOutput();

        $command->setIo(new SymfonyStyle($input, $output));
    }
}
