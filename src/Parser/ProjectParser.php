<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Parser;

use LegacyAnalyzer\Graph\DependencyVisitor;
use LegacyAnalyzer\Model\Codebase;
use LegacyAnalyzer\Model\FileError;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Walks a project directory, parses every .php file and produces the
 * Codebase (intermediate representation) consumed by all later phases.
 *
 * Files with syntax errors are recorded as FileError and skipped: a
 * legacy project must be analyzable even when parts of it are broken.
 */
final class ProjectParser
{
    private const DEFAULT_EXCLUDED_DIRS = ['vendor', 'node_modules', '.git', '.svn', 'cache'];

    private readonly Parser $parser;

    /** @var list<string> */
    private readonly array $excludedDirs;

    /**
     * @param list<string> $extraExcludedDirs Directory names (not paths) to skip
     */
    public function __construct(array $extraExcludedDirs = [])
    {
        // Newest grammar: PHP 5/7 legacy syntax is almost entirely a
        // subset, and files using removed constructs surface as
        // FileError instead of crashing the run.
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->excludedDirs = array_values(array_unique([
            ...self::DEFAULT_EXCLUDED_DIRS,
            ...$extraExcludedDirs,
        ]));
    }

    /**
     * Effective exclusion list: defaults + user-provided extras.
     *
     * @return list<string>
     */
    public function excludedDirs(): array
    {
        return $this->excludedDirs;
    }

    public function parse(string $projectPath): ParseResult
    {
        $root = realpath($projectPath);

        if ($root === false || !is_dir($root)) {
            throw new \InvalidArgumentException("Not a readable directory: {$projectPath}");
        }

        $codebase = new Codebase();
        $classReferences = [];
        $methodCalls = [];
        $functionCalls = [];
        $dynamicReferences = [];

        foreach ($this->phpFiles($root) as $file) {
            $relativePath = $this->relativize($root, $file->getPathname());
            $codebase->incrementFileCount();

            $code = @file_get_contents($file->getPathname());

            if ($code === false) {
                $codebase->addError(new FileError($relativePath, 'File could not be read'));
                continue;
            }

            try {
                $ast = $this->parser->parse($code) ?? [];
            } catch (Error $e) {
                $codebase->addError(new FileError($relativePath, $e->getMessage()));
                continue;
            }

            $collector = new SymbolCollector($relativePath);
            $dependencyVisitor = new DependencyVisitor($relativePath);
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new NameResolver());
            $traverser->addVisitor($collector);
            $traverser->addVisitor($dependencyVisitor);
            $traverser->traverse($ast);

            foreach ($collector->classLikes() as $classLike) {
                $codebase->addClassLike($classLike);
            }

            foreach ($collector->functions() as $function) {
                $codebase->addFunction($function);
            }

            $classReferences = [...$classReferences, ...$dependencyVisitor->classReferences()];
            $methodCalls = [...$methodCalls, ...$dependencyVisitor->methodCalls()];
            $functionCalls = [...$functionCalls, ...$dependencyVisitor->functionCalls()];
            $dynamicReferences = [...$dynamicReferences, ...$dependencyVisitor->dynamicReferences()];
        }

        return new ParseResult(
            codebase: $codebase,
            classReferences: $classReferences,
            methodCalls: $methodCalls,
            functionCalls: $functionCalls,
            dynamicReferences: $dynamicReferences,
        );
    }

    /** @return iterable<SplFileInfo> */
    private function phpFiles(string $root): iterable
    {
        $dirIterator = new RecursiveDirectoryIterator(
            $root,
            RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS,
        );

        $filtered = new RecursiveCallbackFilterIterator(
            $dirIterator,
            fn (SplFileInfo $current): bool => $current->isDir()
                ? !in_array($current->getFilename(), $this->excludedDirs, true)
                : strtolower($current->getExtension()) === 'php',
        );

        /** @var iterable<SplFileInfo> */
        return new RecursiveIteratorIterator($filtered);
    }

    private function relativize(string $root, string $path): string
    {
        $relative = str_starts_with($path, $root)
            ? substr($path, strlen($root) + 1)
            : $path;

        // Normalize separators so reports look identical on any OS.
        return str_replace('\\', '/', $relative);
    }
}
