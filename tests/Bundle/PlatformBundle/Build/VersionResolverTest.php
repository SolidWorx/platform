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

namespace SolidWorx\Platform\Tests\Bundle\PlatformBundle\Build;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidWorx\Platform\PlatformBundle\Build\VersionResolver;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[CoversClass(VersionResolver::class)]
final class VersionResolverTest extends TestCase
{
    private string $dir;

    private Filesystem $filesystem;

    #[Override]
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->dir = sys_get_temp_dir() . '/platform-build-' . bin2hex(random_bytes(6));
        $this->filesystem->mkdir($this->dir);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->filesystem->remove($this->dir);
    }

    public function testReturnsDevVersionOutsideGitCheckout(): void
    {
        self::assertMatchesRegularExpression('/^dev-\d{14}$/', (new VersionResolver())->resolve($this->dir));
    }

    public function testReturnsBranchNameOnABranch(): void
    {
        $this->initRepository();
        $this->git(['checkout', '-b', 'feature/binary-build']);

        self::assertSame('feature/binary-build', (new VersionResolver())->resolve($this->dir));
    }

    public function testPrefersAnExactTagOnHead(): void
    {
        $this->initRepository();
        $this->git(['tag', 'v2.4.0']);

        self::assertSame('v2.4.0', (new VersionResolver())->resolve($this->dir));
    }

    private function initRepository(): void
    {
        $this->git(['init', '--initial-branch=main']);
        $this->git(['config', 'user.email', 'test@example.com']);
        $this->git(['config', 'user.name', 'Test']);
        $this->filesystem->dumpFile($this->dir . '/README.md', 'test');
        $this->git(['add', '.']);
        $this->git(['commit', '-m', 'initial']);
    }

    /**
     * @param list<string> $command
     */
    private function git(array $command): void
    {
        // Running inside this repo's own pre-commit hook (which runs this very test suite) leaves
        // GIT_DIR and friends pointing at that repo; clear them so these commands act on $this->dir.
        (new Process(['git', ...$command], $this->dir, [
            'GIT_DIR' => false,
            'GIT_WORK_TREE' => false,
            'GIT_INDEX_FILE' => false,
            'GIT_PREFIX' => false,
            'GIT_COMMON_DIR' => false,
        ]))->mustRun();
    }
}
