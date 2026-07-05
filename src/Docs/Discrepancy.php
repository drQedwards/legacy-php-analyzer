<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Docs;

/**
 * A documentation-vs-code disagreement worth a human look.
 */
final readonly class Discrepancy
{
    public function __construct(
        /** "App\Foo::bar" or a function FQN */
        public string $location,
        public DiscrepancyKind $kind,
        public string $detail,
        public string $file,
        public int $line,
    ) {
    }
}
