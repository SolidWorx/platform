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

namespace SolidWorx\Platform\PlatformBundle\Build;

use RuntimeException;

/**
 * A build-static.sh invocation failed. Carries the name of the step and the log file it was
 * streamed to, so the command can report exactly what to look at without guessing.
 */
final class BuildStepFailedException extends RuntimeException
{
    public function __construct(
        public readonly string $step,
        public readonly string $logPath,
        string $message,
    ) {
        parent::__construct($message);
    }
}
