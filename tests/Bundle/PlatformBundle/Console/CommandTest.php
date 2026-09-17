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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Console;

use LogicException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SolidWorx\Platform\PlatformBundle\Console\Command;
use SolidWorx\Platform\PlatformBundle\Console\IO;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Covers the shared base class every platform console command extends
 * (`BuildCommand`, `UpdateDisposableDomainsCommand`, `GenerateSchemaCommand` and others): the
 * "IO must be set before handle() runs" guarantee, and the two ways a command's IO legitimately
 * gets set — via `ConsoleCommandEventSubscriber` before run() (the pattern most commands rely on),
 * or by the command itself inside initialize() (the pattern BuildCommand relies on).
 */
#[CoversClass(Command::class)]
#[UsesClass(IO::class)]
final class CommandTest extends TestCase
{
    public function testHandleIsNeverReachedWhenIoWasNeverSet(): void
    {
        $command = new class() extends Command {
            #[Override]
            protected function handle(): int
            {
                // A regression that lets execution reach here would otherwise be invisible: the
                // expected LogicException must come from the guard, not from anything handle()
                // itself might do.
                throw new RuntimeException('handle() must not run when no IO has been set.');
            }
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The IO object has not been set on the command');

        $command->run(new ArrayInput([]), new NullOutput());
    }

    public function testSelfInitializingCommandRunsSuccessfully(): void
    {
        // The case the run()/execute() split exists for: a command that builds its own IO inside
        // initialize() — as BuildCommand does — rather than relying on an external caller.
        $command = new class() extends Command {
            #[Override]
            protected function initialize(InputInterface $input, OutputInterface $output): void
            {
                $this->setIo(new IO($input, $output));
            }

            #[Override]
            protected function handle(): int
            {
                return self::SUCCESS;
            }
        };

        $exitCode = $command->run(new ArrayInput([]), new BufferedOutput());

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExternallyInitializedCommandRunsSuccessfully(): void
    {
        // The pattern the other platform commands rely on: ConsoleCommandEventSubscriber calls
        // setIo() before run() is ever invoked. This is the case that would silently keep passing
        // even if the guard's move from run() to execute() broke something, so it must stay covered
        // alongside the self-initializing case above.
        $command = new class() extends Command {
            #[Override]
            protected function handle(): int
            {
                return self::SUCCESS;
            }
        };

        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $command->setIo(new IO($input, $output));

        $exitCode = $command->run($input, $output);

        self::assertSame(Command::SUCCESS, $exitCode);
    }
}
