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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Command;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Command\GenerateSchemaCommand;
use SolidWorx\Platform\PlatformBundle\Config\SchemaGeneratorInterface;
use SolidWorx\Platform\PlatformBundle\Console\IO;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(GenerateSchemaCommand::class)]
final class GenerateSchemaCommandTest extends TestCase
{
    private string $tmpFile;

    #[Override]
    protected function setUp(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'platform_schema_test_');
        self::assertIsString($tmpFile);
        $this->tmpFile = $tmpFile;
    }

    #[Override]
    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testCommandName(): void
    {
        self::assertSame('platform:generate-schema', $this->makeCommand()->getName());
    }

    public function testCommandDescription(): void
    {
        self::assertNotEmpty($this->makeCommand()->getDescription());
    }

    public function testCommandHasOutputOption(): void
    {
        $definition = $this->makeCommand()->getDefinition();
        self::assertTrue($definition->hasOption('output'));
    }

    public function testOutputOptionHasShortcut(): void
    {
        $option = $this->makeCommand()->getDefinition()->getOption('output');
        self::assertSame('o', $option->getShortcut());
    }

    public function testSchemaGeneratorIsInvoked(): void
    {
        $mockSchema = [
            '$schema' => 'https://example.com',
            'type' => 'object',
            'properties' => [],
        ];

        $generator = $this->createMock(SchemaGeneratorInterface::class);
        $generator->expects(self::once())
            ->method('generate')
            ->willReturn($mockSchema);

        $this->runCommand($this->makeCommand($generator), [
            '--output' => $this->tmpFile,
        ]);
    }

    public function testWritesJsonToOutputFile(): void
    {
        $mockSchema = [
            '$schema' => 'https://example.com',
            'type' => 'object',
            'properties' => [],
        ];

        $generator = $this->createMock(SchemaGeneratorInterface::class);
        $generator->method('generate')->willReturn($mockSchema);

        $this->runCommand($this->makeCommand($generator), [
            '--output' => $this->tmpFile,
        ]);

        self::assertFileExists($this->tmpFile);
        $content = file_get_contents($this->tmpFile);
        self::assertNotFalse($content);
        self::assertNotEmpty($content);
    }

    public function testWrittenFileContainsValidJson(): void
    {
        $mockSchema = [
            '$schema' => 'test',
            'type' => 'object',
        ];

        $generator = $this->createMock(SchemaGeneratorInterface::class);
        $generator->method('generate')->willReturn($mockSchema);

        $this->runCommand($this->makeCommand($generator), [
            '--output' => $this->tmpFile,
        ]);

        $content = file_get_contents($this->tmpFile);
        self::assertNotFalse($content);
        $decoded = json_decode($content, true);
        self::assertSame($mockSchema, $decoded);
    }

    public function testJsonIsPrettyPrinted(): void
    {
        $mockSchema = [
            'type' => 'object',
            'properties' => [
                'key' => [
                    'type' => 'string',
                ],
            ],
        ];

        $generator = $this->createMock(SchemaGeneratorInterface::class);
        $generator->method('generate')->willReturn($mockSchema);

        $this->runCommand($this->makeCommand($generator), [
            '--output' => $this->tmpFile,
        ]);

        $content = file_get_contents($this->tmpFile);
        self::assertNotFalse($content);
        // Pretty-printed JSON contains newlines
        self::assertStringContainsString("\n", $content);
    }

    public function testSuccessOutputContainsOutputFilePath(): void
    {
        $generator = $this->createMock(SchemaGeneratorInterface::class);
        $generator->method('generate')->willReturn([]);

        $output = $this->runCommand($this->makeCommand($generator), [
            '--output' => $this->tmpFile,
        ]);

        self::assertStringContainsString('JSON Schema written to', $output);
    }

    public function testReturnsSuccessExitCode(): void
    {
        $generator = $this->createMock(SchemaGeneratorInterface::class);
        $generator->method('generate')->willReturn([]);

        $command = $this->makeCommand($generator);
        $definition = $command->getDefinition();
        $input = new ArrayInput([
            '--output' => $this->tmpFile,
        ], $definition);
        $bufferedOutput = new BufferedOutput();

        $command->setIo(new IO($input, $bufferedOutput));
        $exitCode = $command->run($input, $bufferedOutput);

        self::assertSame(0, $exitCode);
    }

    public function testDefaultOutputFileNameIsPlatformSchemaJson(): void
    {
        $generator = $this->createMock(SchemaGeneratorInterface::class);
        $generator->method('generate')->willReturn([]);

        $command = $this->makeCommand($generator);
        $option = $command->getDefinition()->getOption('output');

        self::assertSame('platform-schema.json', $option->getDefault());
    }

    private function makeCommand(?SchemaGeneratorInterface $generator = null): GenerateSchemaCommand
    {
        $generator ??= $this->createMock(SchemaGeneratorInterface::class);
        return new GenerateSchemaCommand($generator);
    }

    /**
     * @param array<string, mixed> $inputArgs
     */
    private function runCommand(GenerateSchemaCommand $command, array $inputArgs = []): string
    {
        $definition = $command->getDefinition();
        $input = new ArrayInput($inputArgs, $definition);
        $output = new BufferedOutput();

        $command->setIo(new IO($input, $output));
        $command->run($input, $output);

        return $output->fetch();
    }
}
