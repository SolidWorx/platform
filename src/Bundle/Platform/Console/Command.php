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
     * Set the run method to final to ensure the function is not overridden
     * @throws LogicException|ExceptionInterface
     */
    #[Override]
    final public function run(InputInterface $input, OutputInterface $output): int
    {
        if (! isset($this->io)) {
            throw new LogicException('The IO object has not been set on the command');
        }

        return parent::run($input, $output);
    }

    /**
     * Set the execute method to final to ensure the function is not overridden.
     * All command functionality should be implemented in the handle method.
     */
    #[Override]
    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->handle();
    }

    abstract protected function handle(): int;
}
