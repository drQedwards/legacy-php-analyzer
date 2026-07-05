<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Parser;

use PhpParser\Node;

/**
 * Converts a php-parser type node into a readable string.
 *
 * Class names arrive here already resolved to FQCNs by the NameResolver
 * visitor, so "Collection" under `use Illuminate\Support\Collection`
 * becomes "Illuminate\Support\Collection".
 */
final class TypeStringifier
{
    public static function stringify(?Node $type): ?string
    {
        if ($type === null) {
            return null;
        }

        if ($type instanceof Node\NullableType) {
            return '?' . self::stringify($type->type);
        }

        if ($type instanceof Node\UnionType) {
            return implode('|', array_map(self::stringify(...), $type->types));
        }

        if ($type instanceof Node\IntersectionType) {
            return implode('&', array_map(self::stringify(...), $type->types));
        }

        if ($type instanceof Node\Name || $type instanceof Node\Identifier) {
            return $type->toString();
        }

        return null;
    }
}
