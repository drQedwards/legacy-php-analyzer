<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Cli;

/**
 * Parsed command-line options. Hand-rolled on purpose (no framework):
 * only `--flag` and `--option=value` forms are supported, which keeps
 * the parser a single loop.
 */
final readonly class CliOptions
{
    /**
     * @param list<string> $excludeDirs
     */
    private function __construct(
        public ?string $path,
        public ?string $output,
        public ?string $html,
        public array $excludeDirs,
        public bool $pretty,
        public bool $verbose,
        public bool $failOnDeadCode,
        public bool $help,
    ) {
    }

    /**
     * @param list<string> $argv full argv, script name included
     *
     * @throws UsageException
     */
    public static function fromArgv(array $argv): self
    {
        $path = null;
        $output = null;
        $html = null;
        $excludeDirs = [];
        $pretty = false;
        $verbose = false;
        $failOnDeadCode = false;
        $help = false;

        foreach (array_slice($argv, 1) as $arg) {
            if ($arg === '--help' || $arg === '-h') {
                $help = true;
            } elseif ($arg === '--pretty') {
                $pretty = true;
            } elseif ($arg === '--verbose') {
                $verbose = true;
            } elseif ($arg === '--fail-on-dead-code') {
                $failOnDeadCode = true;
            } elseif (str_starts_with($arg, '--output=')) {
                $output = substr($arg, 9);

                if ($output === '') {
                    throw new UsageException('--output requires a file path: --output=analysis.json');
                }
            } elseif (str_starts_with($arg, '--html=')) {
                $html = substr($arg, 7);

                if ($html === '') {
                    throw new UsageException('--html requires a file path: --html=report.html');
                }
            } elseif (str_starts_with($arg, '--exclude=')) {
                // ADDS to the built-in defaults (vendor, node_modules,
                // .git, ...). Never replaces them.
                foreach (explode(',', substr($arg, 10)) as $dir) {
                    $dir = trim($dir, " \t/\\");

                    if ($dir !== '') {
                        $excludeDirs[] = $dir;
                    }
                }
            } elseif (str_starts_with($arg, '-')) {
                throw new UsageException("Unknown option: {$arg}");
            } elseif ($path === null) {
                $path = $arg;
            } else {
                throw new UsageException("Unexpected extra argument: {$arg}");
            }
        }

        if (!$help && $path === null) {
            throw new UsageException('Missing required argument: <project-path>');
        }

        return new self($path, $output, $html, $excludeDirs, $pretty, $verbose, $failOnDeadCode, $help);
    }
}
