<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Parser;

use LegacyAnalyzer\Graph\DynamicReference;
use LegacyAnalyzer\Graph\FunctionCallRef;
use LegacyAnalyzer\Graph\MethodCallRef;
use LegacyAnalyzer\Graph\RawReference;
use LegacyAnalyzer\Model\Codebase;

/**
 * Everything the parsing phase produces: declared symbols (Codebase)
 * plus every reference collected on the way. Later phases consume this
 * without ever touching the AST again.
 */
final readonly class ParseResult
{
    /**
     * @param list<RawReference>     $classReferences
     * @param list<MethodCallRef>    $methodCalls
     * @param list<FunctionCallRef>  $functionCalls
     * @param list<DynamicReference> $dynamicReferences
     */
    public function __construct(
        public Codebase $codebase,
        public array $classReferences,
        public array $methodCalls,
        public array $functionCalls,
        public array $dynamicReferences,
    ) {
    }
}
