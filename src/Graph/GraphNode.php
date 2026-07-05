<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Graph;

final readonly class GraphNode
{
    public function __construct(
        public string $name,
        public NodeKind $kind,
    ) {
    }
}
