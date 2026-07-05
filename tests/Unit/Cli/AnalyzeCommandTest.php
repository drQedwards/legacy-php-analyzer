<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Tests\Unit\Cli;

use LegacyAnalyzer\Cli\AnalyzeCommand;
use LegacyAnalyzer\Cli\ExitCode;
use LegacyAnalyzer\Output\JsonReporter;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class AnalyzeCommandTest extends TestCase
{
    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    protected function setUp(): void
    {
        $this->stdout = fopen('php://memory', 'r+');
        $this->stderr = fopen('php://memory', 'r+');
    }

    /** @param list<string> $args */
    private function runAnalyze(array $args): int
    {
        $command = new AnalyzeCommand($this->stdout, $this->stderr);

        return $command->run(['bin/analyze', ...$args]);
    }

    private function stdout(): string
    {
        rewind($this->stdout);

        return stream_get_contents($this->stdout) ?: '';
    }

    private function stderr(): string
    {
        rewind($this->stderr);

        return stream_get_contents($this->stderr) ?: '';
    }

    private static function fixture(string $name): string
    {
        return dirname(__DIR__, 2) . '/fixtures/' . $name;
    }

    // --- exit codes ---------------------------------------------------------

    public function testHelpGoesToStdoutAndExitsZero(): void
    {
        self::assertSame(ExitCode::OK, $this->runAnalyze(['--help']));
        self::assertStringContainsString('Usage:', $this->stdout());
        self::assertSame('', $this->stderr());
    }

    public function testMissingPathIsUsageError(): void
    {
        self::assertSame(ExitCode::USAGE_ERROR, $this->runAnalyze([]));
        self::assertStringContainsString('Missing required argument', $this->stderr());
        self::assertSame('', $this->stdout(), 'stdout must stay machine-clean');
    }

    public function testUnknownOptionIsUsageError(): void
    {
        self::assertSame(ExitCode::USAGE_ERROR, $this->runAnalyze(['--bogus', self::fixture('simple-project')]));
        self::assertStringContainsString('Unknown option: --bogus', $this->stderr());
    }

    public function testNonexistentPathIsInputError(): void
    {
        self::assertSame(ExitCode::INPUT_ERROR, $this->runAnalyze(['C:/definitely/not/here']));
        self::assertStringContainsString('not a readable directory', $this->stderr());
    }

    public function testDirectoryWithoutPhpFilesIsInputError(): void
    {
        self::assertSame(ExitCode::INPUT_ERROR, $this->runAnalyze([self::fixture('empty-project')]));
        self::assertStringContainsString('no PHP files found', $this->stderr());
    }

    public function testFindingsAloneDoNotFailTheRun(): void
    {
        // simple-project HAS dead code; default exit must still be 0.
        self::assertSame(ExitCode::OK, $this->runAnalyze([self::fixture('simple-project')]));
    }

    public function testFailOnDeadCodeOptsIntoExitThree(): void
    {
        self::assertSame(
            ExitCode::DEAD_CODE_FOUND,
            $this->runAnalyze(['--fail-on-dead-code', self::fixture('simple-project')]),
        );
    }

    // --- JSON output -----------------------------------------------------------

    public function testStdoutIsValidJsonWithMetaBlock(): void
    {
        $this->runAnalyze([self::fixture('simple-project')]);

        $data = json_decode($this->stdout(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(JsonReporter::SCHEMA_VERSION, $data['meta']['schemaVersion']);
        self::assertSame('legacy-php-analyzer', $data['meta']['tool']['name']);
        self::assertSame(10, $data['meta']['filesScanned']);
        self::assertSame(1, $data['meta']['filesWithParseErrors']);
        self::assertContains('vendor', $data['meta']['excludedDirs']);
        self::assertNotEmpty($data['meta']['generatedAt']);
        self::assertNotEmpty($data['meta']['analyzedPath']);
    }

    public function testJsonCarriesAllFourSections(): void
    {
        $this->runAnalyze([self::fixture('simple-project')]);

        $data = json_decode($this->stdout(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('broken/Broken.php', $data['errors'][0]['file']);
        self::assertNotEmpty($data['graph']['nodes']);
        self::assertNotEmpty($data['graph']['edges']);
        self::assertContains(
            'App\Dog::unusedInternal',
            array_column($data['deadCode']['methods'], 'identifier'),
        );
        self::assertArrayHasKey('coverage', $data['docs']);
    }

    public function testPrettyPrinting(): void
    {
        $this->runAnalyze(['--pretty', self::fixture('simple-project')]);

        self::assertStringStartsWith("{\n", $this->stdout());
    }

    public function testOutputFlagWritesFileAndKeepsStdoutClean(): void
    {
        $target = sys_get_temp_dir() . '/legacy-analyzer-test-' . uniqid() . '.json';

        try {
            self::assertSame(
                ExitCode::OK,
                $this->runAnalyze(['--output=' . $target, self::fixture('simple-project')]),
            );

            self::assertSame('', $this->stdout());
            self::assertStringContainsString($target, $this->stderr());
            self::assertFileExists($target);
            json_decode((string) file_get_contents($target), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            @unlink($target);
        }
    }

    public function testHtmlFlagWritesStandaloneReport(): void
    {
        $target = sys_get_temp_dir() . '/legacy-analyzer-test-' . uniqid() . '.html';

        try {
            self::assertSame(
                ExitCode::OK,
                $this->runAnalyze(['--html=' . $target, self::fixture('simple-project')]),
            );

            self::assertStringContainsString($target, $this->stderr());
            self::assertFileExists($target);

            $html = (string) file_get_contents($target);
            self::assertStringStartsWith('<!DOCTYPE html>', $html);
            self::assertStringNotContainsString('__ANALYSIS_JSON__', $html);
            self::assertStringContainsString('"schemaVersion"', $html);
        } finally {
            @unlink($target);
        }
    }

    // --- excludes -----------------------------------------------------------

    public function testExcludeAddsToDefaultsInsteadOfReplacing(): void
    {
        $this->runAnalyze(['--exclude=broken', self::fixture('simple-project')]);

        $data = json_decode($this->stdout(), true, 512, JSON_THROW_ON_ERROR);

        // broken/ skipped: its parse error is gone...
        self::assertSame(0, $data['meta']['filesWithParseErrors']);
        // ...and vendor/ is STILL excluded (defaults survive).
        self::assertNotContains(
            'VendorClass',
            array_column($data['graph']['nodes'], 'name'),
        );
        self::assertContains('vendor', $data['meta']['excludedDirs']);
        self::assertContains('broken', $data['meta']['excludedDirs']);
    }

    // --- parse error visibility ---------------------------------------------

    public function testParseErrorCountAlwaysInSummary(): void
    {
        $this->runAnalyze([self::fixture('simple-project')]);

        self::assertStringContainsString('1 file(s) could not be parsed', $this->stderr());
        self::assertStringNotContainsString('broken/Broken.php', $this->stderr());
    }

    public function testVerboseListsBrokenFiles(): void
    {
        $this->runAnalyze(['--verbose', self::fixture('simple-project')]);

        self::assertStringContainsString('broken/Broken.php', $this->stderr());
        self::assertStringContainsString('Syntax error', $this->stderr());
    }
}
