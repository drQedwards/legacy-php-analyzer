<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Output;

use LegacyAnalyzer\DeadCode\DeadCodeReport;
use LegacyAnalyzer\Docs\DocReport;
use LegacyAnalyzer\Graph\DependencyGraph;
use LegacyAnalyzer\Parser\ParseResult;

/**
 * Everything one analysis run produced, plus the execution context the
 * reporters need for the meta block.
 */
final readonly class AnalysisResult
{
    /**
     * @param list<string> $excludedDirs effective list (defaults + user)
     */
    public function __construct(
        public ParseResult $parseResult,
        public DependencyGraph $graph,
        public DeadCodeReport $deadCode,
        public DocReport $docs,
        public string $analyzedPath,
        public array $excludedDirs,
        /** ISO-8601 */
        public string $generatedAt,
    ) {
    }

    public function deadFindingsCount(): int
    {
        return count($this->deadCode->classes)
            + count($this->deadCode->methods)
            + count($this->deadCode->functions);
    }
}
