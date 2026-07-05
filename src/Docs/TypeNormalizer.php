<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Docs;

/**
 * Canonicalizes type expressions for COMPARISON, not display.
 * "?string" and "string|null" are the same type written by different
 * generations of the same legacy project; comparing raw strings would
 * flood the report with false discrepancies. Rules:
 *
 *  - "?X"            → {x, null}
 *  - legacy aliases  → integer=int, boolean=bool, double/real=float,
 *                      true/false=bool, callback=callable
 *  - $this / static / self → the declaring class (late static binding
 *    differences are irrelevant for doc-vs-signature checks)
 *  - "Dog[]", "list<Dog>", "array<int, Dog>" → array (the docblock
 *    REFINES the native type; that is documentation, not a mismatch)
 *  - class names compared by SHORT name: docblocks are not run through
 *    NameResolver, so "Dog" in a docblock must match "App\Dog"
 *  - "mixed" / "scalar" / "number" → null (uncomparable: too vague to
 *    call anything a discrepancy)
 *
 * Returns a sorted, deduplicated list — or null when no meaningful
 * comparison is possible.
 */
final class TypeNormalizer
{
    private const ALIASES = [
        'integer' => 'int',
        'boolean' => 'bool',
        'double' => 'float',
        'real' => 'float',
        'true' => 'bool',
        'false' => 'bool',
        'callback' => 'callable',
    ];

    private const UNCOMPARABLE = ['mixed', 'scalar', 'number'];

    private const CONTAINER_GENERICS = ['array', 'list', 'non-empty-array', 'non-empty-list'];

    /** @return list<string>|null */
    public static function canonicalize(?string $type, ?string $selfFqcn = null): ?array
    {
        if ($type === null || trim($type) === '') {
            return null;
        }

        $type = trim($type);
        $parts = [];
        $nullable = false;

        foreach (self::splitUnion($type) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (str_starts_with($part, '?')) {
                $nullable = true;
                $part = substr($part, 1);
            }

            $part = ltrim($part, '\\');

            // Dog[] / Dog[][] → array
            if (preg_match('/\[\s*\]$/', $part) === 1) {
                $parts[] = 'array';

                continue;
            }

            // Generics: keep the base, container bases collapse to array.
            if (preg_match('/^([^<]+)<.*>$/s', $part, $m) === 1) {
                $part = trim($m[1]);
            }

            $lower = strtolower($part);

            if (in_array($lower, self::UNCOMPARABLE, true)) {
                return null;
            }

            if (in_array($lower, self::CONTAINER_GENERICS, true)) {
                $parts[] = 'array';

                continue;
            }

            if ($lower === '$this' || $lower === 'static' || $lower === 'self') {
                $parts[] = $selfFqcn !== null ? self::shortName($selfFqcn) : 'self';

                continue;
            }

            if (isset(self::ALIASES[$lower])) {
                if (self::ALIASES[$lower] === 'bool' && ($lower === 'true' || $lower === 'false')) {
                    $parts[] = 'bool';
                } else {
                    $parts[] = self::ALIASES[$lower];
                }

                continue;
            }

            // Everything else: built-in name or class short name.
            $parts[] = self::shortName($lower);
        }

        if ($nullable) {
            $parts[] = 'null';
        }

        if ($parts === []) {
            return null;
        }

        $parts = array_values(array_unique($parts));
        sort($parts);

        return $parts;
    }

    /**
     * Splits on `|` at nesting depth 0 (respects generics brackets).
     *
     * @return list<string>
     */
    private static function splitUnion(string $type): array
    {
        $parts = [];
        $depth = 0;
        $current = '';

        foreach (str_split($type) as $char) {
            if ($char === '<' || $char === '(') {
                ++$depth;
            } elseif ($char === '>' || $char === ')') {
                --$depth;
            }

            if ($char === '|' && $depth === 0) {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }

        $parts[] = $current;

        return $parts;
    }

    private static function shortName(string $name): string
    {
        $pos = strrpos($name, '\\');

        return strtolower($pos === false ? $name : substr($name, $pos + 1));
    }
}
