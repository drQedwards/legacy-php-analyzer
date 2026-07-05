<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Graph;

/**
 * A directed, typed edge: "$from references $to as $type at $file:$line".
 *
 * `member` carries the referenced member name when the edge points at
 * one: the method of a STATIC_CALL, the constant of a CLASS_CONST
 * ("class" for ::class), the property of a STATIC_PROPERTY. Null when
 * the member is dynamic or the edge type has no member.
 */
final readonly class Edge
{
    public function __construct(
        public string $from,
        public string $to,
        public EdgeType $type,
        public string $file,
        public int $line,
        public ?string $member = null,
    ) {
    }
}
