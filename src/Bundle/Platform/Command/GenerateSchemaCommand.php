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

namespace SolidWorx\Platform\PlatformBundle\Command;

use Override;
use SolidWorx\Platform\PlatformBundle\Config\SchemaGenerator;
use SolidWorx\Platform\PlatformBundle\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use function is_string;
use function json_encode;
use function sprintf;

#[AsCommand(
    name: 'platform:generate-schema',
    description: 'Generates the JSON Schema for platform.yaml IDE autocompletion.',
)]
final class GenerateSchemaCommand extends Command
{
    private const string DEFAULT_OUTPUT = 'platform-schema.json';

    public function __construct(
        private readonly SchemaGenerator $schemaGenerator,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path', self::DEFAULT_OUTPUT);
    }

    #[Override]
    protected function handle(): int
    {
        $outputFile = $this->io->getOption('output');
        if (! is_string($outputFile)) {
            $outputFile = self::DEFAULT_OUTPUT;
        }

        $schema = $this->schemaGenerator->generate();

        $json = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        file_put_contents($outputFile, $json);

        $this->io->success(sprintf('JSON Schema written to %s', $outputFile));

        return self::SUCCESS;
    }
}
