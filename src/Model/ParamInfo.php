<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Model;

/**
 * Immutable representation of a single function/method parameter.
 */
final readonly class ParamInfo
{
    public function __construct(
        public string $name,
        public ?string $type,
        public bool $hasDefault,
        public bool $byRef,
        public bool $variadic,
    ) {
    }
}
