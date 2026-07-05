<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Docs;

/**
 * How much of the project carries any documentation at all — the
 * headline number for an undocumented legacy codebase.
 */
final readonly class DocCoverage
{
    public function __construct(
        public int $classLikesTotal,
        public int $classLikesDocumented,
        public int $methodsTotal,
        public int $methodsDocumented,
        public int $functionsTotal,
        public int $functionsDocumented,
    ) {
    }
}
