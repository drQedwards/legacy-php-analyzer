<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Model;

/**
 * A trait method alias inside a `use Trait { ... }` block:
 *
 *     use LoggerTrait { log as protected writeLog; }
 *
 * Needed by the dead-code detector (design decision #7): an alias counts
 * as a usage of the original trait method, and calls to the alias name
 * must be mapped back to it.
 */
final readonly class TraitAliasInfo
{
    public function __construct(
        /** FQCN of the trait, or null when omitted (single-trait use). */
        public ?string $trait,
        /** Original method name in the trait. */
        public string $method,
        /** New name, or null when only visibility changes. */
        public ?string $alias,
        /** New visibility, or null when unchanged. */
        public ?Visibility $newVisibility,
    ) {
    }
}
