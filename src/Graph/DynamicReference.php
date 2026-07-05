<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Graph;

/**
 * A reference whose target cannot be resolved statically:
 * `new $class()`, `$var::method()`, `$obj->$name()`, `$fn()`, ...
 *
 * These never become edges, but they are counted and reported: a file
 * full of dynamic references makes dead-code findings less trustworthy,
 * and the report says so instead of pretending certainty.
 */
final readonly class DynamicReference
{
    public function __construct(
        /** One of: new, static-call, static-property, class-const, instanceof, method-call, function-call */
        public string $kind,
        public string $source,
        public string $file,
        public int $line,
    ) {
    }
}
