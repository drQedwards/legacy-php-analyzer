<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Graph;

/**
 * A class reference as collected from the AST, before graph resolution.
 *
 * `source` is the enclosing scope: a class-like FQCN, "function:{fqn}"
 * inside a global function, or DependencyGraph::GLOBAL_SCOPE for
 * top-level script code (very common in legacy projects).
 */
final readonly class RawReference
{
    public function __construct(
        public string $source,
        public string $target,
        public EdgeType $kind,
        public ?string $member,
        public string $file,
        public int $line,
    ) {
    }
}
