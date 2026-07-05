<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Docs;

/**
 * Minimal hand-rolled PHPDoc parser: summary, @param, @return and the
 * {@inheritdoc} marker. Deliberately not a full PHPDoc implementation —
 * keeping it small and readable is the point of this project.
 */
final class PhpDocParser
{
    public static function parse(?string $docComment): ?ParsedDoc
    {
        if ($docComment === null || trim($docComment) === '') {
            return null;
        }

        $isInheritDoc = preg_match('/\{@inheritdoc\}/i', $docComment) === 1
            || preg_match('/^\s*\/?\*+\s*@inheritdoc\s*\*?\/?\s*$/im', $docComment) === 1;

        $lines = self::cleanLines($docComment);

        [$summaryLines, $tagLines] = self::splitSummaryAndTags($lines);

        $summary = self::firstParagraph($summaryLines);
        $params = [];
        $returnType = null;
        $returnDescription = null;

        foreach (self::joinTagContinuations($tagLines) as $tag) {
            if (str_starts_with($tag, '@param')) {
                $params[] = self::parseParamTag(trim(substr($tag, 6)));
            } elseif (str_starts_with($tag, '@return')) {
                [$returnType, $returnDescription] = self::parseReturnTag(trim(substr($tag, 7)));
            }
        }

        return new ParsedDoc(
            summary: $summary,
            params: $params,
            returnType: $returnType,
            returnDescription: $returnDescription,
            isInheritDoc: $isInheritDoc,
        );
    }

    /** @return list<string> */
    private static function cleanLines(string $docComment): array
    {
        $text = preg_replace('/^\s*\/\*\*|\*\/\s*$/', '', trim($docComment)) ?? '';
        $lines = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $lines[] = preg_replace('/^\s*\*\s?/', '', $line) ?? '';
        }

        return $lines;
    }

    /**
     * @param  list<string> $lines
     * @return array{0: list<string>, 1: list<string>}
     */
    private static function splitSummaryAndTags(array $lines): array
    {
        $summary = [];
        $tags = [];
        $inTags = false;

        foreach ($lines as $line) {
            if (str_starts_with(ltrim($line), '@')) {
                $inTags = true;
            }

            $inTags ? $tags[] = ltrim($line) : $summary[] = $line;
        }

        return [$summary, $tags];
    }

    /** @param list<string> $lines */
    private static function firstParagraph(array $lines): ?string
    {
        $paragraph = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                if ($paragraph !== []) {
                    break; // first blank line after content ends the summary
                }

                continue; // skip leading blank lines
            }

            // Inline {@inheritdoc} is a marker, not prose.
            $withoutMarker = trim(preg_replace('/\{@inheritdoc\}/i', '', $trimmed) ?? '');

            if ($withoutMarker !== '') {
                $paragraph[] = $withoutMarker;
            }
        }

        return $paragraph === [] ? null : implode(' ', $paragraph);
    }

    /**
     * Re-attaches wrapped tag descriptions to their tag line.
     *
     * @param  list<string> $tagLines
     * @return list<string>
     */
    private static function joinTagContinuations(array $tagLines): array
    {
        $tags = [];

        foreach ($tagLines as $line) {
            if (str_starts_with($line, '@')) {
                $tags[] = $line;
            } elseif ($tags !== [] && trim($line) !== '') {
                $tags[count($tags) - 1] .= ' ' . trim($line);
            }
        }

        return $tags;
    }

    private static function parseParamTag(string $rest): DocParamTag
    {
        $tokens = preg_split('/\s+/', $rest, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $varIndex = null;

        foreach ($tokens as $i => $token) {
            // Accepts $x, &$x, ...$x, &...$x
            if (preg_match('/^[&.]*\$\w+$/', $token) === 1) {
                $varIndex = $i;
                break;
            }
        }

        if ($varIndex === null) {
            // `@param string` without a variable name: unusable for
            // comparison, keep only the type for display.
            return new DocParamTag(
                type: $tokens === [] ? null : implode(' ', $tokens),
                name: null,
                variadicClaimed: false,
                description: null,
            );
        }

        $varToken = $tokens[$varIndex];
        $type = $varIndex > 0 ? implode(' ', array_slice($tokens, 0, $varIndex)) : null;
        $description = implode(' ', array_slice($tokens, $varIndex + 1));

        return new DocParamTag(
            type: $type,
            name: preg_match('/\$(\w+)/', $varToken, $m) === 1 ? $m[1] : null,
            variadicClaimed: str_contains($varToken, '...'),
            description: $description === '' ? null : $description,
        );
    }

    /** @return array{0: ?string, 1: ?string} */
    private static function parseReturnTag(string $rest): array
    {
        $tokens = preg_split('/\s+/', $rest, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($tokens === []) {
            return [null, null];
        }

        // Generic types may contain spaces ("array<int, string>"):
        // keep appending tokens until <> are balanced.
        $type = array_shift($tokens);

        while (
            $tokens !== []
            && substr_count($type, '<') > substr_count($type, '>')
        ) {
            $type .= ' ' . array_shift($tokens);
        }

        $description = implode(' ', $tokens);

        return [$type, $description === '' ? null : $description];
    }
}
