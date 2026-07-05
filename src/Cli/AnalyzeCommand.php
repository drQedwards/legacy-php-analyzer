<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Cli;

use LegacyAnalyzer\DeadCode\DeadCodeDetector;
use LegacyAnalyzer\Docs\DocGenerator;
use LegacyAnalyzer\Graph\GraphBuilder;
use LegacyAnalyzer\Output\AnalysisResult;
use LegacyAnalyzer\Output\HtmlReporter;
use LegacyAnalyzer\Output\JsonReporter;
use LegacyAnalyzer\Parser\ProjectParser;

/**
 * CLI entry point. Stream discipline:
 *
 *   stdout — machine output only (the JSON document, unless --output
 *            redirects it to a file). `analyze proj | jq .` just works.
 *   stderr — everything meant for humans: summary, warnings, errors.
 */
final class AnalyzeCommand
{
    public const VERSION = '0.1.0';

    private const USAGE = <<<'TXT'
        Legacy PHP Analyzer — dependency graph, dead code and docs for legacy projects

        Usage:
          php bin/analyze <project-path> [options]

        Options:
          --output=FILE         Write the JSON report to FILE instead of stdout
          --html=FILE           Also write a standalone interactive HTML report
                                (dependency graph, dead code and docs; opens
                                offline, no network needed)
          --exclude=DIRS        Comma-separated directory NAMES to skip, added on
                                top of the defaults (vendor, node_modules, .git,
                                .svn, cache) — never replacing them
          --pretty              Pretty-print the JSON output
          --verbose             List every file that could not be parsed
          --fail-on-dead-code   Exit with code 3 when any dead code is found (CI)
          -h, --help            Show this help

        Exit codes:
          0  analysis completed (findings or not)
          1  invalid usage / unknown option
          2  path not found, unreadable, or contains no PHP files
          3  dead code found (only with --fail-on-dead-code)
        TXT;

    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(
        private $stdout,
        private $stderr,
    ) {
    }

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        try {
            $options = CliOptions::fromArgv($argv);
        } catch (UsageException $e) {
            $this->err('Error: ' . $e->getMessage());
            $this->err('');
            $this->err(self::USAGE);

            return ExitCode::USAGE_ERROR;
        }

        if ($options->help) {
            $this->out(self::USAGE);

            return ExitCode::OK;
        }

        $realPath = realpath((string) $options->path);

        if ($realPath === false || !is_dir($realPath)) {
            $this->err("Error: not a readable directory: {$options->path}");

            return ExitCode::INPUT_ERROR;
        }

        $parser = new ProjectParser($options->excludeDirs);
        $parseResult = $parser->parse($realPath);
        $codebase = $parseResult->codebase;

        if ($codebase->fileCount() === 0) {
            $this->err("Error: no PHP files found in {$realPath}");

            return ExitCode::INPUT_ERROR;
        }

        $graph = (new GraphBuilder())->build($parseResult);
        $deadCode = (new DeadCodeDetector())->detect($parseResult, $graph);
        $docs = (new DocGenerator())->generate($codebase);

        $analysis = new AnalysisResult(
            parseResult: $parseResult,
            graph: $graph,
            deadCode: $deadCode,
            docs: $docs,
            analyzedPath: $realPath,
            excludedDirs: $parser->excludedDirs(),
            generatedAt: date(DATE_ATOM),
        );

        $json = (new JsonReporter())->toJson($analysis, $options->pretty);

        if ($options->output !== null) {
            if (@file_put_contents($options->output, $json . "\n") === false) {
                $this->err("Error: could not write to {$options->output}");

                return ExitCode::INPUT_ERROR;
            }

            $this->err("JSON report written to {$options->output}");
        } else {
            fwrite($this->stdout, $json . "\n");
        }

        if ($options->html !== null) {
            try {
                $htmlReport = (new HtmlReporter())->render($analysis);
            } catch (\RuntimeException $e) {
                $this->err('Error: ' . $e->getMessage());

                return ExitCode::INPUT_ERROR;
            }

            if (@file_put_contents($options->html, $htmlReport) === false) {
                $this->err("Error: could not write to {$options->html}");

                return ExitCode::INPUT_ERROR;
            }

            $this->err("HTML report written to {$options->html}");
        }

        $this->printSummary($analysis, $options->verbose);

        if ($options->failOnDeadCode && $analysis->deadFindingsCount() > 0) {
            return ExitCode::DEAD_CODE_FOUND;
        }

        return ExitCode::OK;
    }

    private function printSummary(AnalysisResult $analysis, bool $verbose): void
    {
        $codebase = $analysis->parseResult->codebase;
        $errors = $codebase->errors();
        $coverage = $analysis->docs->coverage;
        $dead = $analysis->deadCode;

        $this->err('');
        $this->err('Legacy PHP Analyzer v' . self::VERSION);
        $this->err('Analyzed: ' . $analysis->analyzedPath);
        $this->err(sprintf(
            'Files: %d scanned | Symbols: %d class-likes, %d functions',
            $codebase->fileCount(),
            count($codebase->classLikes()),
            count($codebase->functions()),
        ));
        $this->err(sprintf(
            'Graph: %d nodes, %d edges',
            count($analysis->graph->nodes()),
            count($analysis->graph->edges()),
        ));
        $this->err(sprintf(
            'Dead code: %d classes, %d methods, %d functions',
            count($dead->classes),
            count($dead->methods),
            count($dead->functions),
        ));
        $this->err(sprintf(
            'Docs: %d discrepancies | method coverage %d/%d',
            count($analysis->docs->discrepancies),
            $coverage->methodsDocumented,
            $coverage->methodsTotal,
        ));

        if ($errors !== []) {
            $this->err(sprintf(
                'WARNING: %d file(s) could not be parsed and were NOT analyzed%s',
                count($errors),
                $verbose ? ':' : ' (rerun with --verbose for the list)',
            ));

            if ($verbose) {
                foreach ($errors as $error) {
                    $this->err("  - {$error->file}: {$error->message}");
                }
            }
        }
    }

    private function out(string $line): void
    {
        fwrite($this->stdout, $line . "\n");
    }

    private function err(string $line): void
    {
        fwrite($this->stderr, $line . "\n");
    }
}
