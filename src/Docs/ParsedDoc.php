<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Docs;

/**
 * Light structured view of a PHPDoc block: exactly what the doc
 * generator needs, nothing more.
 */
final readonly class ParsedDoc
{
    /**
     * @param list<DocParamTag> $params
     */
    public function __construct(
        public ?string $summary,
        public array $params,
        public ?string $returnType,
        public ?string $returnDescription,
        public bool $isInheritDoc,
    ) {
    }

    public function paramByName(string $name): ?DocParamTag
    {
        foreach ($this->params as $param) {
            if ($param->name !== null && strcasecmp($param->name, $name) === 0) {
                return $param;
            }
        }

        return null;
    }
}
