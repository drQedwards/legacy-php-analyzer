<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Model;

/**
 * Aggregate root produced by the parsing phase: every symbol found in
 * the analyzed project.
 *
 * The collection is filled incrementally while files are parsed, but
 * every entry (ClassLikeInfo, FunctionInfo, ...) is immutable.
 *
 * Lookups are case-insensitive because PHP class and function names are
 * case-insensitive: legacy code referencing `new USER()` must match a
 * class declared as `User`.
 */
final class Codebase
{
    /** @var array<string, ClassLikeInfo> keyed by lowercased FQCN */
    private array $classLikes = [];

    /** @var array<string, FunctionInfo> keyed by lowercased FQN */
    private array $functions = [];

    /** @var list<FileError> */
    private array $errors = [];

    private int $fileCount = 0;

    public function addClassLike(ClassLikeInfo $classLike): void
    {
        $this->classLikes[strtolower($classLike->fqcn)] = $classLike;
    }

    public function addFunction(FunctionInfo $function): void
    {
        $this->functions[strtolower($function->fqn)] = $function;
    }

    public function addError(FileError $error): void
    {
        $this->errors[] = $error;
    }

    public function incrementFileCount(): void
    {
        ++$this->fileCount;
    }

    public function classLike(string $fqcn): ?ClassLikeInfo
    {
        return $this->classLikes[strtolower(ltrim($fqcn, '\\'))] ?? null;
    }

    public function hasClassLike(string $fqcn): bool
    {
        return $this->classLike($fqcn) !== null;
    }

    public function function(string $fqn): ?FunctionInfo
    {
        return $this->functions[strtolower(ltrim($fqn, '\\'))] ?? null;
    }

    /** @return list<ClassLikeInfo> */
    public function classLikes(): array
    {
        return array_values($this->classLikes);
    }

    /** @return list<FunctionInfo> */
    public function functions(): array
    {
        return array_values($this->functions);
    }

    /** @return list<FileError> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function fileCount(): int
    {
        return $this->fileCount;
    }
}
