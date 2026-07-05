<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Docs;

/**
 * A parameter as presented in the generated documentation. The
 * signature is authoritative; the docblock fills in what it can.
 */
final readonly class ParamDoc
{
    public function __construct(
        public string $name,
        /** Signature type when present, docblock type as fallback. */
        public ?string $type,
        public bool $variadic,
        public bool $hasDefault,
        public ?string $description,
    ) {
    }
}
