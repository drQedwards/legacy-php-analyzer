<?php

declare(strict_types=1);

namespace LegacyAnalyzer\DeadCode;

/**
 * Result of the dead-code phase. `dynamicReferenceCounts` is part of
 * the report on purpose: a project riddled with `new $class` deserves
 * to see WHY its findings are only medium confidence.
 */
final readonly class DeadCodeReport
{
    /**
     * @param list<DeadSymbol>   $classes   Never-referenced class-likes
     * @param list<DeadSymbol>   $methods   Unreachable private/protected methods
     * @param list<DeadSymbol>   $functions Never-called free functions
     * @param array<string, int> $dynamicReferenceCounts by DynamicReference kind
     */
    public function __construct(
        public array $classes,
        public array $methods,
        public array $functions,
        public array $dynamicReferenceCounts,
    ) {
    }
}
