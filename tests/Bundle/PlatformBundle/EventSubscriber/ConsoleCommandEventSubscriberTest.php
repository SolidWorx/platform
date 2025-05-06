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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Console\Command;
use SolidWorx\Platform\PlatformBundle\EventSubscriber\ConsoleCommandEventSubscriber;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

#[CoversClass(ConsoleCommandEventSubscriber::class)]
final class ConsoleCommandEventSubscriberTest extends TestCase
{
    public function testHandlesCommandWithSymfonyStyle(): void
    {
        $commandMock = $this->createMock(Command::class);

        $commandMock->expects($this->once())
            ->method('setIo')
            ->with(self::isInstanceOf(SymfonyStyle::class));

        $eventMock = new ConsoleCommandEvent($commandMock, new ArrayInput([]), new NullOutput());

        $subscriber = new ConsoleCommandEventSubscriber();
        $subscriber->onConsoleEvent($eventMock);
    }

    #[DoesNotPerformAssertions]
    public function testSkipsNonCommandInstances(): void
    {
        $command = new class() extends SymfonyCommand {
            public function setIo(SymfonyStyle $io): void
            {
                ConsoleCommandEventSubscriberTest::fail('setIo should not be called');
            }
        };

        $event = new ConsoleCommandEvent($command, new ArrayInput([]), new NullOutput());

        $subscriber = new ConsoleCommandEventSubscriber();
        $subscriber->onConsoleEvent($event);
    }
}
