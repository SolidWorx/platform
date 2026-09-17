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

use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;
use Override;
use SolidWorx\Platform\PlatformBundle\Build\AppArchiver;
use SolidWorx\Platform\PlatformBundle\Build\BuildOptions;
use SolidWorx\Platform\PlatformBundle\Build\Preflight;
use SolidWorx\Platform\PlatformBundle\Build\Problem;
use SolidWorx\Platform\PlatformBundle\Build\StaticBuilder;
use SolidWorx\Platform\PlatformBundle\Build\VersionResolver;
use SolidWorx\Platform\PlatformBundle\Console\Command;
use SolidWorx\Platform\PlatformBundle\Console\IO;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Throwable;
use function array_filter;
use function array_slice;
use function array_values;
use function count;
use function file;
use function filesize;
use function implode;
use function is_file;
use function is_string;
use function microtime;
use function number_format;
use function sprintf;
use function str_contains;

#[AsCommand(
    name: 'platform:build|platform:compile',
    description: 'Compiles the application into a single static executable.',
)]
final class BuildCommand extends Command
{
    private const int LOG_TAIL_LINES = 30;

    public function __construct(
        private readonly Preflight $preflight,
        private readonly VersionResolver $versionResolver,
        private readonly AppArchiver $archiver,
        private readonly StaticBuilder $builder,
        private readonly string $projectDir,
        /**
         * @var array<string, mixed>
         */
        private readonly array $buildConfig,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            // Named "app-version" rather than "version": every Symfony Console Application reserves
            // a global "--version"/"-V" option (VALUE_NONE, prints the console app's own version),
            // and mergeApplicationDefinition() throws a LogicException the moment a command tries to
            // register an incompatible option under that same name.
            ->addOption('app-version', mode: InputOption::VALUE_REQUIRED, description: 'Version stamped into the binary. Defaults to the current tag, branch or commit.')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Directory the binary is written to.')
            ->addOption('php-version', mode: InputOption::VALUE_REQUIRED, description: 'PHP version to compile.')
            ->addOption('clean', mode: InputOption::VALUE_NONE, description: 'Wipe the build cache before building.')
            ->addOption('skip-checks', mode: InputOption::VALUE_NONE, description: 'Skip the application sanity checks.')
            ->addOption('dry-run', mode: InputOption::VALUE_NONE, description: 'Run the checks and build the archive, then report what would be built.');
    }

    #[Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // Mirrors what ConsoleCommandEventSubscriber does at runtime, so the command also works
        // when constructed directly — as the tests do.
        $this->setIo(new IO($input, $output));
    }

    #[Override]
    protected function handle(): int
    {
        $started = microtime(as_float: true);

        $version = $this->option('app-version') ?? $this->versionResolver->resolve($this->projectDir);

        $options = BuildOptions::fromConfig($this->buildConfig, $version, [
            'output_dir' => $this->option('output'),
            'php_version' => $this->option('php-version'),
        ]);

        $this->io->title(sprintf('%s %s — static binary', $options->appName, $options->version));

        if (! $this->reportPreflight()) {
            return self::FAILURE;
        }

        $archive = $this->archiver->archive(
            $this->projectDir,
            $options->workDir . '/frankenphp/app.tar.gz',
            $options->exclude,
            $options->outputDir,
        );

        $this->io->writeln(sprintf(
            ' <info>✓</info> Archive         %s files · %s',
            number_format($archive->fileCount),
            $this->formatBytes($archive->bytes),
        ));

        $platform = $this->builder->hostPlatform();
        $binaryName = $options->binaryFileName($platform['os'], $platform['arch']);

        if ($this->io->getOption('dry-run') === true) {
            $this->io->newLine();
            $this->io->writeln(sprintf(' Dry run — would build <info>%s/%s</info>', $options->outputDir, $binaryName));
            $this->io->writeln(sprintf(' Work directory  %s', $options->workDir));

            return self::SUCCESS;
        }

        $this->io->writeln(' <comment>…</comment> PHP runtime     building static PHP — the first run takes 30–60 minutes');

        try {
            $produced = $this->builder->build(
                $options,
                $this->projectDir,
                fn (string $buffer): null => $this->stream($buffer),
                $this->io->getOption('clean') === true,
            );
        } catch (Throwable $throwable) {
            $this->io->newLine();
            $this->io->error($throwable->getMessage());
            $this->showLogTail($options);

            return self::FAILURE;
        }

        $destination = $options->outputDir . '/' . $binaryName;

        $filesystem = new Filesystem();
        $filesystem->mkdir($options->outputDir);
        $filesystem->rename($produced, $destination, overwrite: true);
        $filesystem->chmod($destination, 0o755);

        $size = filesize($destination);
        $verified = $this->smokeTest($destination, $options->version);

        $this->io->newLine();
        $this->io->writeln(sprintf(' Binary    <info>%s</info> (%s)', $destination, $this->formatBytes($size === false ? 0 : $size)));
        $this->io->writeln(sprintf(' Version   %s', $options->version));
        $this->io->writeln(sprintf(' Duration  %s', $this->formatDuration(microtime(as_float: true) - $started)));
        $this->io->newLine();

        if (! $verified) {
            // The binary is kept: an engineer can run it by hand to find out why it does not report
            // its own version, which deleting it would make impossible.
            $this->io->error(sprintf(
                'The binary was produced but its "version" output did not contain %s — it is unverified. '
                . 'This usually means the -X ldflags chain is broken (the Go linker silently drops an -X '
                . 'flag whose target symbol does not exist), so the binary carries the wrong identity even '
                . 'though it built successfully.',
                $options->version,
            ));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Runs the finished binary's own `version` command and checks the printed output, not merely
     * the exit status. The application's identity reaches the binary through `go build -ldflags
     * -X`, and the Go linker silently ignores an -X flag whose target symbol does not exist — no
     * error, no warning. A mis-wired ldflags chain therefore produces a perfectly working binary
     * that reports the wrong version. Exit status cannot see that; the printed version can.
     */
    private function smokeTest(string $binary, string $version): bool
    {
        $process = new Process([$binary, 'version'], timeout: 60);
        $process->run();

        return $process->isSuccessful() && str_contains($process->getOutput(), $version);
    }

    /**
     * Reports the preflight result and says whether the build may continue.
     */
    private function reportPreflight(): bool
    {
        $problems = $this->preflight->run($this->projectDir, $this->io->getOption('skip-checks') === true);
        $errors = array_values(array_filter($problems, static fn (Problem $problem): bool => $problem->isError()));
        if ($problems === []) {
            $this->io->writeln(sprintf(' <info>✓</info> Toolchain       %s', implode(' · ', $this->preflight->toolVersions())));
            $this->io->writeln(' <info>✓</info> Application     dependencies and assets in place');

            return true;
        }

        foreach ($problems as $problem) {
            $this->io->writeln(sprintf(
                ' <%1$s>%2$s</%1$s> %3$s',
                $problem->isError() ? 'error' : 'comment',
                $problem->isError() ? '✗' : '⚠',
                $problem->title,
            ));
            $this->io->writeln(sprintf('   %s', $problem->detail));
        }

        $this->io->newLine();
        return $errors === [];
    }

    private function stream(string $buffer): null
    {
        if ($this->io->isVerbose()) {
            $this->io->write($buffer);
        }

        return null;
    }

    private function showLogTail(BuildOptions $options): void
    {
        $log = $options->workDir . '/frankenphp/dist/static-php-cli/log/go-build.log';

        if (! is_file($log)) {
            return;
        }

        $contents = file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_slice($contents === false ? [] : $contents, -self::LOG_TAIL_LINES);

        $this->io->writeln($lines);
        $this->io->writeln(sprintf(' Full log: %s', $log));
    }

    private function option(string $name): ?string
    {
        $value = $this->io->getOption($name);

        return is_string($value) ? $value : null;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            ++$unit;
        }

        return sprintf('%.1f %s', $value, $units[$unit]);
    }

    private function formatDuration(float $seconds): string
    {
        $minutes = (int) ($seconds / 60);

        return sprintf('%dm %02ds', $minutes, (int) $seconds % 60);
    }
}
