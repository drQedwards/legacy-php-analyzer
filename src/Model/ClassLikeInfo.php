<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Model;

/**
 * Immutable representation of a class, interface, trait or enum.
 *
 * All names (`extends`, `implements`, `traits`) are fully qualified,
 * already resolved by php-parser's NameResolver.
 */
final readonly class ClassLikeInfo
{
    /**
     * @param list<string>              $extends          Parent class (0..1 entry) or parent
     *                                                    interfaces (interfaces allow several)
     * @param list<string>              $implements       Implemented interfaces (classes/enums only)
     * @param list<string>              $traits           Traits consumed via `use`
     * @param list<TraitAliasInfo>      $traitAliases
     * @param list<TraitPrecedenceInfo> $traitPrecedences
     * @param list<MethodInfo>          $methods
     */
    public function __construct(
        public string $fqcn,
        public string $shortName,
        public ClassLikeKind $kind,
        public bool $isAbstract,
        public bool $isFinal,
        public array $extends,
        public array $implements,
        public array $traits,
        public array $traitAliases,
        public array $traitPrecedences,
        public array $methods,
        public ?string $docComment,
        public string $file,
        public int $startLine,
    ) {
    }

    public function method(string $name): ?MethodInfo
    {
        foreach ($this->methods as $method) {
            // Method names are case-insensitive in PHP.
            if (strcasecmp($method->name, $name) === 0) {
                return $method;
            }
        }

        return null;
    }
}
