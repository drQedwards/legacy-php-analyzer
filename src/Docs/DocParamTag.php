<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Docs;

/**
 * One parsed `@param` tag, as written in the docblock.
 */
final readonly class DocParamTag
{
    public function __construct(
        public ?string $type,
        /** Bare name without $, & or ... — null for malformed tags. */
        public ?string $name,
        /** True when the docblock itself wrote `...$name`. */
        public bool $variadicClaimed,
        public ?string $description,
    ) {
    }
}
