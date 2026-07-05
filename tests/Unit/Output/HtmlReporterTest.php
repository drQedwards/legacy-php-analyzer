<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Tests\Unit\Output;

use LegacyAnalyzer\DeadCode\DeadCodeDetector;
use LegacyAnalyzer\Docs\DocGenerator;
use LegacyAnalyzer\Graph\GraphBuilder;
use LegacyAnalyzer\Output\AnalysisResult;
use LegacyAnalyzer\Output\HtmlReporter;
use LegacyAnalyzer\Parser\ProjectParser;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class HtmlReporterTest extends TestCase
{
    private static AnalysisResult $analysis;

    public static function setUpBeforeClass(): void
    {
        $parser = new ProjectParser();
        $result = $parser->parse(dirname(__DIR__, 2) . '/fixtures/simple-project');
        $graph = (new GraphBuilder())->build($result);

        self::$analysis = new AnalysisResult(
            parseResult: $result,
            graph: $graph,
            deadCode: (new DeadCodeDetector())->detect($result, $graph),
            docs: (new DocGenerator())->generate($result->codebase),
            analyzedPath: 'C:/fake/simple-project',
            excludedDirs: $parser->excludedDirs(),
            generatedAt: '2026-07-05T12:00:00+00:00',
        );
    }

    public function testRenderReplacesBothPlaceholders(): void
    {
        $html = (new HtmlReporter())->render(self::$analysis);

        self::assertStringStartsWith('<!DOCTYPE html>', $html);
        self::assertStringNotContainsString('__ANALYSIS_JSON__', $html);
        self::assertStringNotContainsString('__VIS_NETWORK_JS__', $html);
        // The vis-network bundle is actually inlined (its UMD banner).
        self::assertStringContainsString('vis-network', $html);
        // The analysis JSON is embedded.
        self::assertStringContainsString('"schemaVersion"', $html);
        self::assertStringContainsString('unusedInternal', $html);
    }

    public function testEmbeddedJsonCannotBreakOutOfScriptTag(): void
    {
        $html = (new HtmlReporter())->render(self::$analysis);

        // JSON_HEX_TAG turns every < and > into < / >, so the
        // embedded document can never contain "</script>". The only
        // occurrences left must belong to the template itself.
        preg_match('/const DATA = (.*);/', $html, $m);
        self::assertNotEmpty($m, 'embedded DATA assignment must exist');
        self::assertStringNotContainsString('<', $m[1]);
        json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
    }

    public function testMissingBundleFailsLoudly(): void
    {
        $reporter = new HtmlReporter(visBundlePath: 'C:/nope/vis-network.min.js');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('vis-network bundle not found');
        $reporter->render(self::$analysis);
    }
}
