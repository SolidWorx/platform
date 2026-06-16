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
use SolidWorx\Platform\PlatformBundle\Command\UpdateDisposableDomainsCommand;
use SolidWorx\Platform\PlatformBundle\Console\IO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use function file_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(UpdateDisposableDomainsCommand::class)]
final class UpdateDisposableDomainsCommandTest extends TestCase
{
    private string $tmpFile;

    #[Override]
    protected function setUp(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'disposable_domains_test_');
        $this->assertIsString($tmpFile);
        $this->tmpFile = $tmpFile;
    }

    #[Override]
    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testWritesDownloadedListToTargetFile(): void
    {
        $list = "disposable-one.test\ndisposable-two.test\n";

        $httpClient = new MockHttpClient(new MockResponse($list));
        $command = new UpdateDisposableDomainsCommand($httpClient, $this->tmpFile);

        $input = new ArrayInput([], $command->getDefinition());
        $output = new BufferedOutput();
        $command->setIo(new IO($input, $output));

        $exitCode = $command->run($input, $output);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame($list, file_get_contents($this->tmpFile));
        $this->assertStringContainsString('blocklist refreshed', $output->fetch());
    }
}
