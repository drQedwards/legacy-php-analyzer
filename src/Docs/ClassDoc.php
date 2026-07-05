<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Docs;

final readonly class ClassDoc
{
    /**
     * @param list<string>    $extends
     * @param list<string>    $implements
     * @param list<string>    $traits
     * @param list<MethodDoc> $methods
     */
    public function __construct(
        public string $fqcn,
        /** class | interface | trait | enum */
        public string $kind,
        public ?string $summary,
        public array $extends,
        public array $implements,
        public array $traits,
        public array $methods,
        public bool $documented,
        public string $file,
        public int $line,
    ) {
    }
}
