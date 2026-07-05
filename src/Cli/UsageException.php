<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Cli;

/**
 * Invalid command-line invocation: unknown flag, missing path, ...
 * Always maps to ExitCode::USAGE_ERROR.
 */
final class UsageException extends \RuntimeException
{
}
