<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Model;

/**
 * A file that could not be parsed. Legacy projects often contain broken
 * or half-migrated files; the analyzer records the error and moves on
 * instead of aborting the whole run.
 */
final readonly class FileError
{
    public function __construct(
        public string $file,
        public string $message,
    ) {
    }
}
