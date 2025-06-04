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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class IO extends SymfonyStyle
{
    public function __construct(
        private readonly InputInterface $input,
        OutputInterface $output,
    ) {
        parent::__construct($input, $output);
    }

    public function getOption(string $name): mixed
    {
        return $this->input->getOption($name);
    }
}
