<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Model;

/**
 * A trait conflict resolution:
 *
 *     use A, B { A::hello insteadof B; }
 *
 * The excluded trait's method is NOT available in the consuming class,
 * which matters when expanding trait methods for dead-code analysis.
 */
final readonly class TraitPrecedenceInfo
{
    /**
     * @param list<string> $insteadOf FQCNs of the traits whose method is excluded
     */
    public function __construct(
        public string $trait,
        public string $method,
        public array $insteadOf,
    ) {
    }
}
