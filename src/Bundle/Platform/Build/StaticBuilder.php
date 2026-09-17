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

use const FILE_APPEND;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use function array_filter;
use function array_values;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function hash_file;
use function implode;
use function is_file;
use function php_uname;
use function preg_match;
use function sprintf;
use function str_contains;
use function strtolower;
use function trim;

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
     * - a double quote, backslash, newline, or carriage return cannot break the quoting on their
     *   own (only the apostrophe actually confuses go's `quoted.Split`), but none of them are
     *   values any real config would legitimately need, so they are rejected too as cheap
     *   insurance against whatever the next upstream change to the quoting does.
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
        $target = $this->stagedDir($options);

        $this->filesystem->mkdir($target);

        // app.tar.gz, app_checksum.txt and dist/ are the build's own OUTPUT, written into this same
        // Resources/build source tree by a developer who once ran (or is running) a build straight
        // out of the source checkout — exactly what .gitignore anticipates by listing them there.
        // BuildCommand has already written the real, freshly-built archive and checksum into
        // $target before this method runs; copying stale ones from $this->sourceDir over them would
        // silently ship an old archive instead of the one just built. dist/ is excluded (not merely
        // skipped file-by-file) because static-php-cli's own checkout underneath it can run into the
        // gigabytes, and Finder would otherwise have to walk all of it for nothing.
        foreach (new Finder()->files()->in($this->sourceDir)->exclude('dist')->notName(['app.tar.gz', 'app_checksum.txt']) as $file) {
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
     * Where the vendored sources are staged for this build. Pure and side-effect free, so the
     * command can also use it to print what a --dry-run would do without actually staging anything.
     */
    public function stagedDir(BuildOptions $options): string
    {
        return $options->workDir . '/frankenphp';
    }

    /**
     * Where the application archive is extracted to for EMBED. Pure and side-effect free for the
     * same reason as {@see self::stagedDir()}.
     */
    public function embedDir(BuildOptions $options): string
    {
        return $options->workDir . '/embed';
    }

    /**
     * Where the `xcaddy` shim tees the Go linker's own output (see Resources/build/xcaddy):
     * static-php-cli swallows the shim's streams, so this file is the only place a link failure's
     * real message ends up. Pure and side-effect free for the same reason as {@see self::stagedDir()}.
     */
    public function goBuildLogPath(BuildOptions $options): string
    {
        return $this->stagedDir($options) . '/dist/static-php-cli/log/go-build.log';
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
    public function environment(BuildOptions $options, string $embedDir): array
    {
        $this->assertSafeForLdflags($options);

        $environment = [
            // EMBED must be a bounded directory, never the project directory itself:
            // build-static.sh appends `--with-frankenphp-app=${EMBED}` to SPC_OPT_BUILD_ARGS
            // unconditionally (it is not conditional on xcaddy being the real binary), so
            // static-php-cli receives and walks this path regardless of the shim replacing xcaddy.
            // The project directory contains work_dir — itself multi-gigabyte once PHP is compiled
            // — so pointing EMBED at it risked spc traversing its own buildroot. $embedDir is a
            // throwaway extraction of the just-built archive under work_dir instead: bounded,
            // cleaned before every build so no stale extraction survives, and removed again after
            // a successful one.
            'EMBED' => $embedDir,
            'FRANKENPHP_VERSION' => $options->version,
            'PHP_EXTENSION_LIBS' => implode(',', $options->phpExtensionLibs),
            // The zstd extension probes for apc_serializer.h via phpincludedir, which a static build
            // with an empty prefix leaves unset. Pointing it at the PHP source tree satisfies the
            // check without patching static-php-cli.
            'phpincludedir' => $this->stagedDir($options) . '/dist/static-php-cli/source/php-src',
            'PLATFORM_APP_SOURCE_DIR' => $this->stagedDir($options),
            'PLATFORM_APP_NAME' => $options->appName,
            'PLATFORM_APP_DESCRIPTION' => $options->description,
            'PLATFORM_APP_PORT' => $options->defaultPort,
            'PLATFORM_APP_ENV_PREFIX' => $options->envPrefix,
            'PLATFORM_APP_INSTALL_CHECK' => $options->installCheckCommand ?? '',
            'PLATFORM_APP_BOOT_COMMANDS' => implode(',', $options->bootCommands),
            'PLATFORM_APP_VERSION' => $options->version,
        ];

        // Always exported, even as an empty string: build-static.sh treats an empty value and an
        // unset one identically (it tests `[ -z "${VAR}" ]`), but Symfony's Process inherits the
        // whole parent environment, so an omitted key here would fall through to whatever a
        // developer's shell, CI image, or PHP version manager happens to already export under
        // that name — silently compiling a different PHP version or extension set than
        // platform.yaml asked for. Only an explicit empty string shadows that.
        $environment['PHP_VERSION'] = $options->phpVersion ?? '';

        $extensions = $options->resolveExtensions($this->defaultExtensions());
        $environment['PHP_EXTENSIONS'] = implode(',', $extensions);

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
     * Validates configuration that would otherwise only fail at the very end of the build (see
     * {@see self::assertSafeForLdflags()}). Public so the command can run it early — before the
     * archive is even built — on every path, including --dry-run.
     */
    public function validate(BuildOptions $options): void
    {
        $this->assertSafeForLdflags($options);
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
    public function build(BuildOptions $options, callable $onOutput, bool $clean): string
    {
        // Cheap and config-only: catching a typo here costs nothing, catching it after the staging
        // and compile steps below costs the whole build. The command already calls validate() much
        // earlier than this, but build() may also be called directly, so it re-checks its own input.
        $this->assertSafeForLdflags($options);

        // Every step streams to a log unconditionally, and the log is cleared at the start of every
        // build so a previous successful run's output can never be printed against a later
        // failure — the same stale-artefact hazard AppArchiver already guards against for the
        // archive/checksum pair.
        $logDir = $options->workDir . '/log';
        $this->filesystem->remove($logDir);
        $this->filesystem->mkdir($logDir);

        // The shim itself truncates this on every run that reaches the link step (its `tee` opens
        // the file fresh), but a build that fails earlier — the PHP compile dying, most commonly —
        // never invokes the shim at all and would otherwise leave a previous build's go-build.log
        // sitting there to be printed against this, unrelated, failure.
        $this->filesystem->remove($this->goBuildLogPath($options));

        $staged = $this->stage($options);

        $embedDir = $this->embedDir($options);
        // Cleaned before extracting, not just relied upon to be absent, so a stale extraction left
        // by an interrupted previous build never survives into this one.
        $this->filesystem->remove($embedDir);
        $this->extractEmbed($staged . '/app.tar.gz', $embedDir);

        $environment = $this->environment($options, $embedDir);

        if ($clean) {
            // Owned here rather than delegated to the script. build-static.sh's own CLEAN handling
            // (`rm -Rf dist/` + `go clean -cache`) runs on every invocation, but this method may
            // call the script twice on a cold cache: the first run installs the real xcaddy and
            // compiles PHP, then it gets overwritten with our shim below. Exporting CLEAN would
            // make the *second* run wipe dist/ again, destroying the shim and the freshly compiled
            // PHP right before the link step, reinstalling the real xcaddy, and building stock
            // upstream FrankenPHP instead of the application — with no error, since the binary
            // still ends up at the expected path. Removing the staged dist/ directory once, here,
            // before either run happens, produces a genuinely cold cache without that self-sabotage.
            //
            // go clean -cache is deliberately NOT reproduced: it wipes the machine-wide Go build
            // cache shared by every Go project on the machine, which this command has no business
            // doing to a developer's machine.
            $this->filesystem->remove($staged . '/dist');
        }

        $platform = $this->hostPlatform();
        $xcaddy = $staged . '/' . sprintf(self::XCADDY_PATH, $this->spcArch($platform['arch']), strtolower(php_uname('s')));

        // Nested rather than two sequential checks: run() can turn the outer condition from true to
        // false, so the two are not the same check twice and must not be collapsed into one.
        if (! is_file($xcaddy)) {
            $this->run($staged, $environment, $onOutput, allowFailure: true, step: 'bootstrap', logDir: $logDir);

            if (! is_file($xcaddy)) {
                throw new BuildStepFailedException(
                    'bootstrap',
                    $logDir . '/bootstrap.log',
                    'static-php-cli did not install xcaddy; see the build log for what went wrong.',
                );
            }
        }

        $this->filesystem->copy($staged . '/xcaddy', $xcaddy, overwriteNewerFiles: true);
        $this->filesystem->chmod($xcaddy, 0o755);

        $binary = sprintf('%s/dist/frankenphp-%s-%s', $staged, $platform['os'], $platform['arch']);

        // The script skips the link step when its output already exists, which would silently hand
        // back the previous build.
        $this->filesystem->remove($binary);

        $this->run($staged, $environment, $onOutput, allowFailure: false, step: 'build', logDir: $logDir);

        if (! is_file($binary)) {
            throw new BuildStepFailedException(
                'build',
                $logDir . '/build.log',
                sprintf('The build finished but produced no binary at %s.', $binary),
            );
        }

        // EMBED was only ever a throwaway copy of the archive already sitting in $staged; keeping
        // it around after a successful build would leave the whole application sitting at full size
        // under work_dir for no reason.
        $this->filesystem->remove($embedDir);

        return $binary;
    }

    /**
     * Extracts the just-built application archive into a bounded directory for EMBED, so
     * static-php-cli never has to walk the project directory (and its own multi-gigabyte
     * work_dir) directly. See {@see self::environment()}.
     */
    private function extractEmbed(string $archive, string $embedDir): void
    {
        $this->filesystem->mkdir($embedDir);

        $process = new Process(['tar', '-xzf', $archive, '-C', $embedDir], timeout: null);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'Could not extract %s for EMBED: %s',
                $archive,
                trim($process->getErrorOutput()) !== '' ? trim($process->getErrorOutput()) : 'tar exited with code ' . $process->getExitCode(),
            ));
        }
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
    private function run(string $staged, array $environment, callable $onOutput, bool $allowFailure, string $step, string $logDir): void
    {
        // RELEASE is unset explicitly: upstream uses it to upload to dunglas/frankenphp.
        $environment['RELEASE'] = '';

        // CI is unset explicitly too: when set, build-static.sh:212-215 removes ./downloads and
        // ./source after a run. Our own cold-cache path runs the script twice on purpose (see
        // build()'s doc comment) and needs both directories to survive between the two runs —
        // phpincludedir above also points into source. Upstream's cleanup assumes a single run;
        // left inherited from a real CI environment (our own documented CI usage sets one), the
        // second run would fail. RELEASE is neutralised the same way, for the same reason.
        $environment['CI'] = '';

        $logPath = $logDir . '/' . $step . '.log';

        $process = new Process(['./build-static.sh'], $staged, $environment, timeout: null);

        $process->run(static function (string $type, string $buffer) use ($onOutput, $logPath): void {
            // Written unconditionally, regardless of -v: this is the only durable record of a step
            // that can take 30-60 minutes, and $onOutput only echoes to the console when verbose.
            file_put_contents($logPath, $buffer, FILE_APPEND);
            $onOutput($buffer);
        });

        if (! $allowFailure && ! $process->isSuccessful()) {
            throw new BuildStepFailedException($step, $logPath, sprintf('build-static.sh exited with code %d.', (int) $process->getExitCode()));
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
                    'The %s value "%s" contains a character (apostrophe, double quote, backslash, '
                    . "newline, or carriage return) that breaks build-static.sh's xcaddy shim, which wraps "
                    . 'this value in single quotes to build -ldflags — a problem that otherwise only '
                    . 'surfaces at the very end of the build.',
                    $candidate['key'],
                    $candidate['value'],
                ));
            }
        }
    }
}
