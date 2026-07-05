<?php

declare(strict_types=1);

namespace LegacyAnalyzer\DeadCode;

/**
 * How much to trust a dead-code finding. PHP is too dynamic for
 * certainty, so the detector reports "probably dead" with a level:
 *
 *   High   — no dynamic construct in the project could plausibly
 *            reach this symbol.
 *   Medium — the project contains dynamic references of the relevant
 *            kind (new $x, $x::m(), variable functions...), any of
 *            which could hide a real usage.
 *   Low    — there is concrete counter-evidence (e.g. an instance
 *            call with the same method name on an unknown receiver).
 */
enum Confidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
}
