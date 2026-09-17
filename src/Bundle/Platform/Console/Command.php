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

namespace SolidWorx\Platform\PlatformBundle\Console;

use LogicException;
use Override;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Command extends SymfonyCommand
{
    protected IO $io;

    public function setIo(IO $io): void
    {
        $this->io = $io;
    }

    /**
     * Sealed against subclass overrides so every command's behaviour lives in handle().
     *
     * The IO-readiness check does not live here: initialize() — where a self-initializing command
     * such as BuildCommand sets its own IO — only runs inside parent::run(), so checking any
     * earlier than that would reject it before it had a chance. See execute() below.
     *
     * @throws ExceptionInterface When input binding fails.
     * @throws LogicException     Propagated from execute() when no IO was set.
     */
    #[Override]
    final public function run(InputInterface $input, OutputInterface $output): int
    {
        return parent::run($input, $output);
    }

    /**
     * Set the execute method to final to ensure the function is not overridden.
     * All command functionality should be implemented in the handle method.
     *
     * The IO readiness check lives here rather than in run(): parent::run() calls initialize()
     * before execute(), and a command may build its own IO there (see BuildCommand) instead of
     * relying on ConsoleCommandEventSubscriber to set it beforehand. Checking in run() itself would
     * reject that before initialize() ever had a chance to run.
     */
    #[Override]
    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! isset($this->io)) {
            throw new LogicException('The IO object has not been set on the command');
        }

        return $this->handle();
    }

    abstract protected function handle(): int;
}
