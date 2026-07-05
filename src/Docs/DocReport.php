<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Docs;

final readonly class DocReport
{
    /**
     * @param list<ClassDoc>    $classes       sorted by FQCN
     * @param list<FunctionDoc> $functions     sorted by FQN
     * @param list<Discrepancy> $discrepancies sorted by file, line
     */
    public function __construct(
        public array $classes,
        public array $functions,
        public array $discrepancies,
        public DocCoverage $coverage,
    ) {
    }
}
