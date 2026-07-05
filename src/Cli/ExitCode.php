<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Cli;

/**
 * CI-friendly exit codes. Finding dead code is a SUCCESSFUL analysis
 * (exit 0): a finding is the tool doing its job, not the tool failing.
 * Only --fail-on-dead-code opts into exit 3 for pipelines that want to
 * gate on findings.
 */
final class ExitCode
{
    public const OK = 0;
    public const USAGE_ERROR = 1;
    public const INPUT_ERROR = 2;
    public const DEAD_CODE_FOUND = 3;
}
