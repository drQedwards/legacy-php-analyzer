<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Graph;

/**
 * A call to a free function.
 *
 * PHP resolves unqualified function names at runtime with a namespace
 * fallback: `foo()` inside `namespace App` means `App\foo()` if it
 * exists, else global `foo()`. That cannot be decided statically, so
 * both candidates are recorded and matched against declared functions
 * later.
 */
final readonly class FunctionCallRef
{
    /**
     * @param non-empty-list<string> $candidates FQNs in resolution order
     */
    public function __construct(
        public array $candidates,
        public string $file,
        public int $line,
    ) {
    }
}
