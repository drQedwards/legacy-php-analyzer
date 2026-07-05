<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Graph;

/**
 * An instance method call: `$x->method()`.
 *
 * Not a graph edge (without type inference the receiver's class is
 * unknown), but the dead-code detector needs it:
 *   - onThis = true  → definite call within `class` (its FQCN is known,
 *     it is the enclosing class).
 *   - onThis = false → the method NAME is used somewhere; enough to
 *     lower confidence when a private/protected method of the same
 *     name would otherwise be reported dead.
 */
final readonly class MethodCallRef
{
    public function __construct(
        /** Enclosing class when onThis, null otherwise or in anonymous classes. */
        public ?string $class,
        public string $method,
        public bool $onThis,
        public string $file,
        public int $line,
    ) {
    }
}
