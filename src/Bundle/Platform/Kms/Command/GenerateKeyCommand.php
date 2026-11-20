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

namespace SolidWorx\Platform\PlatformBundle\Kms\Command;

use Override;
use RuntimeException;
use SolidWorx\Platform\PlatformBundle\Console\Command;
use SolidWorx\Platform\PlatformBundle\Kms\LocalKeyManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use function file_exists;
use function sprintf;

#[AsCommand(
    name: 'platform:kms:generate-key',
    description: 'Generates the local master key (sodium+dir:// or openssl+dir:// KMS DSNs only).',
)]
final class GenerateKeyCommand extends Command
{
    public function __construct(
        private readonly LocalKeyManager $localKeys,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('force', mode: InputOption::VALUE_NONE, description: 'Overwrite an existing key; everything encrypted under it becomes unreadable');
    }

    #[Override]
    protected function handle(): int
    {
        $force = $this->io->getOption('force') === true;
        $existing = $this->localKeys->keyPath();

        if (! $force && $existing !== null && file_exists($existing)) {
            $this->io->error(sprintf('The master key %s already exists. Pass --force to replace it; everything encrypted under it becomes unreadable.', $existing));

            return self::FAILURE;
        }

        try {
            $path = $this->localKeys->generate($force);
        } catch (RuntimeException $runtimeException) {
            $this->io->error($runtimeException->getMessage());

            return self::FAILURE;
        }

        $this->io->success(sprintf('Master key written to %s', $path));

        return self::SUCCESS;
    }
}
