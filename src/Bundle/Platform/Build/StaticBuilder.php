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

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use function array_filter;
use function array_values;
use function explode;
use function file_get_contents;
use function hash_file;
use function implode;
use function is_file;
use function php_uname;
use function preg_match;
use function sprintf;
use function str_contains;
use function strtolower;

/**
 * Drives the vendored build-static.sh.
 *
 * The script is an unmodified upstream copy, so everything specific to this platform is expressed
 * as environment variables and as the `xcaddy` shim the script ends up calling.
 */
final readonly class StaticBuilder
{
    /**
     * Where static-php-cli installs the xcaddy it downloads, relative to the staged directory. The
     * shim is copied over this path so the script's own call reaches our build instead.
     */
    private const string XCADDY_PATH = 'dist/static-php-cli/pkgroot/%s-%s/go-xcaddy/bin/xcaddy';

    /**
     * Characters that break out of the single quotes the `xcaddy` shim wraps each of these values
     * in when it assembles `-ldflags` (e.g. `-X 'main.appDescription=${PLATFORM_APP_DESCRIPTION}'`).
     * The go command re-parses that whole string with shell-like quoting, so:
     *
     * - an apostrophe closes the quote early and corrupts every flag written after it — an
     *   entirely ordinary value such as "Pierre's invoicing app" breaks the build;
     * - a double quote or backslash can be re-interpreted once that quoting is broken;
     * - a raw newline or carriage return splits the single-line flags string outright.
     *
     * None of this surfaces until the link step, at the very end of an hour-long build, so these
     * are rejected eagerly by {@see self::assertSafeForLdflags()} instead.
     *
     * @var list<string>
     */
    private const array UNSAFE_LDFLAGS_CHARACTERS = ["'", '"', '\\', "\n", "\r"];

    public function __construct(
        private Filesystem $filesystem,
        private string $sourceDir,
    ) {
    }

    /**
     * Copies the vendored sources into the work directory, leaving unchanged files untouched so Go
     * does not rebuild the world on every run.
     */
    public function stage(BuildOptions $options): string
    {
        $target = $options->workDir . '/frankenphp';

        $this->filesystem->mkdir($target);

        foreach (new Finder()->files()->in($this->sourceDir) as $file) {
            $destination = $target . '/' . $file->getRelativePathname();

            if (is_file($destination) && hash_file('sha256', $destination) === hash_file('sha256', $file->getPathname())) {
                continue;
            }

            $this->filesystem->copy($file->getPathname(), $destination, overwriteNewerFiles: true);
        }

        $this->filesystem->chmod($target . '/build-static.sh', 0o755);
        $this->filesystem->chmod($target . '/xcaddy', 0o755);

        return $target;
    }

    /**
     * The extension set build-static.sh falls back to, read from the script itself so it stays
     * correct across upstream syncs.
     *
     * @return list<string>
     */
    public function defaultExtensions(): array
    {
        $script = file_get_contents($this->sourceDir . '/build-static.sh');

        if ($script === false || preg_match('/^defaultExtensions="([^"]+)"/m', $script, $matches) !== 1) {
            throw new RuntimeException('Could not read the default extension list from build-static.sh.');
        }

        return array_values(array_filter(explode(',', $matches[1]), static fn (string $e): bool => $e !== ''));
    }

    /**
     * @return array<string, string>
     */
    public function environment(BuildOptions $options, string $projectDir): array
    {
        $this->assertSafeForLdflags($options);

        $environment = [
            // EMBED only feeds `spc dump-extensions`, and the archive is this directory, so there is
            // nothing to gain from extracting the tarball to hand over a copy of it.
            'EMBED' => $projectDir,
            'FRANKENPHP_VERSION' => $options->version,
            'PHP_EXTENSION_LIBS' => implode(',', $options->phpExtensionLibs),
            // The zstd extension probes for apc_serializer.h via phpincludedir, which a static build
            // with an empty prefix leaves unset. Pointing it at the PHP source tree satisfies the
            // check without patching static-php-cli.
            'phpincludedir' => $options->workDir . '/frankenphp/dist/static-php-cli/source/php-src',
            'PLATFORM_APP_SOURCE_DIR' => $options->workDir . '/frankenphp',
            'PLATFORM_APP_NAME' => $options->appName,
            'PLATFORM_APP_DESCRIPTION' => $options->description,
            'PLATFORM_APP_PORT' => $options->defaultPort,
            'PLATFORM_APP_ENV_PREFIX' => $options->envPrefix,
            'PLATFORM_APP_INSTALL_CHECK' => $options->installCheckCommand ?? '',
            'PLATFORM_APP_BOOT_COMMANDS' => implode(',', $options->bootCommands),
            'PLATFORM_APP_VERSION' => $options->version,
        ];

        if ($options->phpVersion !== null) {
            $environment['PHP_VERSION'] = $options->phpVersion;
        }

        $extensions = $options->resolveExtensions($this->defaultExtensions());

        // Left unset on purpose when empty: that is the signal build-static.sh uses to derive the
        // extension set from the application's composer.json.
        if ($extensions !== []) {
            $environment['PHP_EXTENSIONS'] = implode(',', $extensions);
        }

        return $environment;
    }

    /**
     * @return array{os: string, arch: string}
     */
    public function hostPlatform(): array
    {
        $os = strtolower(php_uname('s'));

        return [
            'os' => $os === 'darwin' ? 'mac' : $os,
            'arch' => php_uname('m'),
        ];
    }

    /**
     * Runs the build and returns the path of the binary build-static.sh produced.
     *
     * static-php-cli installs its own xcaddy during the first build, then calls it. On a cold cache
     * there is nothing to replace yet, so the script runs once to install it — that run also compiles
     * PHP, which is the expensive part and is reused — and again with the shim in place.
     *
     * @param callable(string): void $onOutput
     */
    public function build(BuildOptions $options, string $projectDir, callable $onOutput, bool $clean): string
    {
        // Cheap and config-only: catching a typo here costs nothing, catching it after the staging
        // and compile steps below costs the whole build.
        $this->assertSafeForLdflags($options);

        $staged = $this->stage($options);
        $environment = $this->environment($options, $projectDir);

        if ($clean) {
            $environment['CLEAN'] = '1';
        }

        $platform = $this->hostPlatform();
        $xcaddy = $staged . '/' . sprintf(self::XCADDY_PATH, $this->spcArch($platform['arch']), strtolower(php_uname('s')));

        // Nested rather than two sequential checks: run() can turn the outer condition from true to
        // false, so the two are not the same check twice and must not be collapsed into one.
        if (! is_file($xcaddy)) {
            $this->run($staged, $environment, $onOutput, allowFailure: true);

            if (! is_file($xcaddy)) {
                throw new RuntimeException('static-php-cli did not install xcaddy; see the build log for what went wrong.');
            }
        }

        $this->filesystem->copy($staged . '/xcaddy', $xcaddy, overwriteNewerFiles: true);
        $this->filesystem->chmod($xcaddy, 0o755);

        $binary = sprintf('%s/dist/frankenphp-%s-%s', $staged, $platform['os'], $platform['arch']);

        // The script skips the link step when its output already exists, which would silently hand
        // back the previous build.
        $this->filesystem->remove($binary);

        $this->run($staged, $environment, $onOutput, allowFailure: false);

        if (! is_file($binary)) {
            throw new RuntimeException(sprintf('The build finished but produced no binary at %s.', $binary));
        }

        return $binary;
    }

    /**
     * static-php-cli names its package root with aarch64/x86_64 rather than the raw uname value.
     */
    private function spcArch(string $arch): string
    {
        return match ($arch) {
            'arm64', 'aarch64' => 'aarch64',
            'x86_64', 'amd64' => 'x86_64',
            default => $arch,
        };
    }

    /**
     * @param array<string, string>  $environment
     * @param callable(string): void $onOutput
     */
    private function run(string $staged, array $environment, callable $onOutput, bool $allowFailure): void
    {
        // RELEASE is unset explicitly: upstream uses it to upload to dunglas/frankenphp.
        $environment['RELEASE'] = '';

        $process = new Process(['./build-static.sh'], $staged, $environment, timeout: null);

        $process->run(static function (string $type, string $buffer) use ($onOutput): void {
            $onOutput($buffer);
        });

        if (! $allowFailure && ! $process->isSuccessful()) {
            throw new RuntimeException(sprintf('build-static.sh exited with code %d.', (int) $process->getExitCode()));
        }
    }

    /**
     * Rejects config values against {@see self::UNSAFE_LDFLAGS_CHARACTERS} before any build work
     * starts. Checked against every value the `xcaddy` shim wraps in single quotes to build
     * `-ldflags`; PLATFORM_APP_SOURCE_DIR is deliberately excluded because the shim only `cd`s into
     * it — that value never enters the -ldflags string.
     *
     * @throws InvalidArgumentException naming the offending key and value.
     */
    private function assertSafeForLdflags(BuildOptions $options): void
    {
        /** @var list<array{key: string, value: string}> $candidates */
        $candidates = [
            [
                'key' => 'name',
                'value' => $options->appName,
            ],
            [
                'key' => 'description',
                'value' => $options->description,
            ],
            [
                'key' => 'default_port',
                'value' => $options->defaultPort,
            ],
            [
                'key' => 'env_prefix',
                'value' => $options->envPrefix,
            ],
            [
                'key' => 'version',
                'value' => $options->version,
            ],
        ];

        if ($options->installCheckCommand !== null) {
            $candidates[] = [
                'key' => 'hooks.install_check',
                'value' => $options->installCheckCommand,
            ];
        }

        foreach ($options->bootCommands as $command) {
            $candidates[] = [
                'key' => 'hooks.on_boot',
                'value' => $command,
            ];
        }

        foreach ($candidates as $candidate) {
            foreach (self::UNSAFE_LDFLAGS_CHARACTERS as $character) {
                if (! str_contains($candidate['value'], $character)) {
                    continue;
                }

                throw new InvalidArgumentException(sprintf(
                    'The %s value "%s" contains a character (apostrophe, double quote, backslash, or '
                    . "newline) that breaks build-static.sh's xcaddy shim, which wraps this value in single "
                    . 'quotes to build -ldflags — a problem that otherwise only surfaces at the very end of '
                    . 'the build.',
                    $candidate['key'],
                    $candidate['value'],
                ));
            }
        }
    }
}
