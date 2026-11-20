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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Kms\Command;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Console\IO;
use SolidWorx\Platform\PlatformBundle\Kms\Command\GenerateKeyCommand;
use SolidWorx\Platform\PlatformBundle\Kms\LocalKeyManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;
use function base64_encode;
use function bin2hex;
use function class_exists;
use function file_get_contents;
use function file_put_contents;
use function filesize;
use function mkdir;
use function random_bytes;
use function str_replace;
use function sys_get_temp_dir;

#[CoversClass(GenerateKeyCommand::class)]
#[UsesClass(IO::class)]
#[UsesClass(LocalKeyManager::class)]
#[RequiresPhpExtension('sodium')]
final class GenerateKeyCommandTest extends TestCase
{
    private string $dir;

    private BufferedOutput $output;

    #[Override]
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/platform-kms-' . bin2hex(random_bytes(6));

        if (! class_exists(Encrypted::class)) {
            self::markTestSkipped('symfony/doctrine-orm-key-management is not installed.');
        }

        $this->output = new BufferedOutput();
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->dir);
    }

    public function testCommandWritesKey(): void
    {
        $this->assertSame(Command::SUCCESS, $this->execute());

        $path = $this->dir . '/platform.key';
        $this->assertSame(32, filesize($path));
        $this->assertStringContainsString($path, $this->display());
    }

    public function testCommandNeverPrintsTheKey(): void
    {
        $this->execute();

        $key = (string) file_get_contents($this->dir . '/platform.key');
        $display = $this->output->fetch();
        $this->assertStringNotContainsString($key, $display);
        $this->assertStringNotContainsString(bin2hex($key), $display);
        $this->assertStringNotContainsString(base64_encode($key), $display);
    }

    public function testCommandRefusesExisting(): void
    {
        mkdir($this->dir, 0700);
        file_put_contents($this->dir . '/platform.key', 'existing');

        $this->assertSame(Command::FAILURE, $this->execute());

        $this->assertSame('existing', file_get_contents($this->dir . '/platform.key'));
        $this->assertStringContainsString('--force', $this->display());
    }

    public function testCommandForceOverwrites(): void
    {
        mkdir($this->dir, 0700);
        file_put_contents($this->dir . '/platform.key', 'existing');

        $this->assertSame(Command::SUCCESS, $this->execute([
            '--force' => true,
        ]));

        $this->assertSame(32, filesize($this->dir . '/platform.key'));
    }

    public function testCommandRejectsRemoteDsn(): void
    {
        $this->assertSame(Command::FAILURE, $this->execute(dsn: 'aws-kms://default?region=eu-west-1'));

        $this->assertStringContainsString('aws-kms', $this->display());
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function execute(array $parameters = [], ?string $dsn = null): int
    {
        $command = new GenerateKeyCommand(new LocalKeyManager(
            $dsn ?? 'sodium+dir://' . $this->dir . '?ext=.key',
            'platform',
            'dev',
            false,
            null,
            null,
        ));

        $input = new ArrayInput($parameters, $command->getDefinition());
        $command->setIo(new IO($input, $this->output));

        return $command->run($input, $this->output);
    }

    private function display(): string
    {
        // SymfonyStyle wraps long lines; a path must still match.
        return str_replace(["\n", ' '], '', $this->output->fetch());
    }
}
