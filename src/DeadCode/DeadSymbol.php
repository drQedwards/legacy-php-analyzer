<?php

declare(strict_types=1);

namespace LegacyAnalyzer\DeadCode;

/**
 * One dead-code finding.
 */
final readonly class DeadSymbol
{
    public function __construct(
        /** "App\Foo", "App\Foo::bar" or "some_function" */
        public string $identifier,
        /** class | interface | trait | enum | method | function */
        public string $type,
        public string $file,
        public int $line,
        public Confidence $confidence,
        public string $reason,
    ) {
    }
}
