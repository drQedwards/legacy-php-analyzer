<?php

declare(strict_types=1);

namespace LegacyAnalyzer\DeadCode;

use LegacyAnalyzer\Graph\DependencyGraph;
use LegacyAnalyzer\Graph\EdgeType;
use LegacyAnalyzer\Model\ClassLikeInfo;
use LegacyAnalyzer\Model\ClassLikeKind;
use LegacyAnalyzer\Model\MethodInfo;
use LegacyAnalyzer\Model\Visibility;
use LegacyAnalyzer\Parser\ParseResult;

/**
 * Flags probably-dead symbols. Core rules:
 *
 *  - Classes: dead when they have zero inbound edges from anything
 *    other than themselves. A dead class's methods are not re-reported.
 *  - Methods: only private/protected methods WITH a body are
 *    candidates. Interface methods, abstract methods and all magic
 *    methods (engine-invoked: __construct, __get, __toString, ...) are
 *    never candidates.
 *  - Trait methods: usage is checked across the TRANSITIVE consumer
 *    closure — traits using the trait, classes using any trait in that
 *    chain, and (for protected) those classes' full hierarchy family.
 *    `insteadof` prunes a consumer branch; an `as` alias counts as
 *    usage of the original method and its name as a valid call name.
 *  - Protected methods: usable from the whole hierarchy family
 *    (ancestors call overridden hooks via $this — template method
 *    pattern — and descendants call inherited helpers).
 *  - Functions: dead when no collected call resolves to them.
 *
 * This is a flat, one-level analysis: calls inside a dead method still
 * keep their targets alive (no transitive reachability).
 */
final class DeadCodeDetector
{
    private const MAGIC_METHODS = [
        '__construct', '__destruct', '__get', '__set', '__isset', '__unset',
        '__call', '__callstatic', '__tostring', '__invoke', '__clone',
        '__sleep', '__wakeup', '__serialize', '__unserialize',
        '__debuginfo', '__set_state',
    ];

    /** @var array<string, ClassLikeInfo> lower FQCN → info */
    private array $classes = [];

    /** @var array<string, list<string>> lower parent → lower children */
    private array $children = [];

    /** @var array<string, list<string>> lower trait → lower direct users */
    private array $traitUsers = [];

    /** @var array<string, array<string, true>> lower enclosing class → lower method names called via $this-> */
    private array $onThisCalls = [];

    /** @var array<string, true> lower method names called on unknown receivers */
    private array $namedInstanceCalls = [];

    /** @var array<string, array<string, true>> lower class → lower members statically referenced */
    private array $staticMembers = [];

    /** @var array<string, true> lower classes targeted by X::$dynamicMethod() */
    private array $staticUnknownMember = [];

    /** @var array<string, true> "lowertrait::lowermethod" with an `as` alias somewhere */
    private array $aliasDeclared = [];

    /** @var array<string, array<string, true>> "lowertrait::lowermethod" → lower alias names */
    private array $aliasNames = [];

    /** @var array<string, array<string, true>> "lowerconsumer::lowermethod" → lower excluded traits */
    private array $insteadofExclusions = [];

    /** @var array<string, int> */
    private array $dynamicCounts = [];

    public function detect(ParseResult $result, DependencyGraph $graph): DeadCodeReport
    {
        $this->buildIndexes($result, $graph);

        $deadClasses = [];
        $deadMethods = [];

        foreach ($result->codebase->classLikes() as $classLike) {
            if ($this->isDeadClass($classLike, $graph)) {
                $deadClasses[] = new DeadSymbol(
                    identifier: $classLike->fqcn,
                    type: $classLike->kind->value,
                    file: $classLike->file,
                    line: $classLike->startLine,
                    confidence: $this->classConfidence(),
                    reason: 'No reference of any kind found in the analyzed project',
                );

                continue; // its methods die with it, no need to repeat
            }

            if ($classLike->kind === ClassLikeKind::InterfaceType) {
                continue; // interface methods are contracts, never candidates
            }

            foreach ($classLike->methods as $method) {
                if (!$this->isCandidate($method)) {
                    continue;
                }

                $finding = $classLike->kind === ClassLikeKind::TraitType
                    ? $this->evaluateTraitMethod($classLike, $method)
                    : $this->evaluateClassMethod($classLike, $method);

                if ($finding !== null) {
                    $deadMethods[] = $finding;
                }
            }
        }

        $deadFunctions = $this->detectDeadFunctions($result);

        return new DeadCodeReport(
            classes: $this->sorted($deadClasses),
            methods: $this->sorted($deadMethods),
            functions: $this->sorted($deadFunctions),
            dynamicReferenceCounts: $this->dynamicCounts,
        );
    }

    // --- index construction ----------------------------------------------

    /**
     * Precomputes every lookup once: the per-method verdicts below run
     * for each candidate, so they must be hash hits, not rescans of the
     * full reference lists. Reset first so the detector can be reused.
     */
    private function buildIndexes(ParseResult $result, DependencyGraph $graph): void
    {
        $this->classes = [];
        $this->children = [];
        $this->traitUsers = [];
        $this->onThisCalls = [];
        $this->namedInstanceCalls = [];
        $this->staticMembers = [];
        $this->staticUnknownMember = [];
        $this->aliasDeclared = [];
        $this->aliasNames = [];
        $this->insteadofExclusions = [];
        $this->dynamicCounts = [];

        foreach ($result->codebase->classLikes() as $classLike) {
            $lower = strtolower($classLike->fqcn);
            $this->classes[$lower] = $classLike;

            foreach ($classLike->extends as $parent) {
                $this->children[$this->key($parent)][] = $lower;
            }

            foreach ($classLike->traits as $trait) {
                $this->traitUsers[$this->key($trait)][] = $lower;
            }

            foreach ($classLike->traitAliases as $alias) {
                $traitKeys = $alias->trait !== null
                    ? [$this->key($alias->trait)]
                    : array_map($this->key(...), $classLike->traits);

                foreach ($traitKeys as $traitKey) {
                    $key = $traitKey . '::' . strtolower($alias->method);
                    $this->aliasDeclared[$key] = true;

                    if ($alias->alias !== null) {
                        $this->aliasNames[$key][strtolower($alias->alias)] = true;
                    }
                }
            }

            foreach ($classLike->traitPrecedences as $precedence) {
                $key = $lower . '::' . strtolower($precedence->method);

                foreach ($precedence->insteadOf as $excludedTrait) {
                    $this->insteadofExclusions[$key][$this->key($excludedTrait)] = true;
                }
            }
        }

        foreach ($result->methodCalls as $call) {
            $method = strtolower($call->method);

            if ($call->onThis && $call->class !== null) {
                $this->onThisCalls[strtolower($call->class)][$method] = true;
            } elseif (!$call->onThis) {
                $this->namedInstanceCalls[$method] = true;
            }
        }

        foreach ($graph->edges() as $edge) {
            if ($edge->type !== EdgeType::StaticCall) {
                continue;
            }

            $target = $this->key($edge->to);

            if ($edge->member === null) {
                $this->staticUnknownMember[$target] = true;
            } else {
                $this->staticMembers[$target][strtolower($edge->member)] = true;
            }
        }

        foreach ($result->dynamicReferences as $dynamic) {
            $this->dynamicCounts[$dynamic->kind] = ($this->dynamicCounts[$dynamic->kind] ?? 0) + 1;
        }
    }

    // --- classes -----------------------------------------------------------

    // Self-references (recursion, `new self` factories) don't count: a
    // class that only talks to itself is still unreachable from outside.
    private function isDeadClass(ClassLikeInfo $classLike, DependencyGraph $graph): bool
    {
        foreach ($graph->edgesTo($classLike->fqcn) as $edge) {
            if (strcasecmp($edge->from, $classLike->fqcn) !== 0) {
                return false; // referenced by someone else
            }
        }

        return true;
    }

    // Any unresolved `new $x` / `$x::m()` could be targeting this class,
    // so a "no references" verdict cannot claim high confidence.
    private function classConfidence(): Confidence
    {
        $relevant = ['new', 'static-call', 'static-property', 'class-const', 'instanceof'];

        foreach ($relevant as $kind) {
            if (($this->dynamicCounts[$kind] ?? 0) > 0) {
                return Confidence::Medium;
            }
        }

        return Confidence::High;
    }

    // --- methods -----------------------------------------------------------

    // Public methods are never candidates: they may be called from
    // outside the analyzed tree (entry points, templates, other packages).
    private function isCandidate(MethodInfo $method): bool
    {
        return $method->visibility !== Visibility::PublicVis
            && $method->hasBody
            && !$method->isAbstract
            && !in_array(strtolower($method->name), self::MAGIC_METHODS, true);
    }

    private function evaluateClassMethod(ClassLikeInfo $classLike, MethodInfo $method): ?DeadSymbol
    {
        $lower = strtolower($classLike->fqcn);
        $isPrivate = $method->visibility === Visibility::PrivateVis;

        // Private: own class + its trait code (traits access the
        // consuming class's private members). Protected: the whole
        // hierarchy family, plus every family member's traits.
        $classScopes = $isPrivate ? [$lower => true] : $this->family($lower);
        $scopes = $this->withTraitClosures($classScopes);

        return $this->verdict(
            $classLike,
            $method,
            scopes: $scopes,
            callNames: [strtolower($method->name) => true],
            staticTargets: $classScopes,
        );
    }

    private function evaluateTraitMethod(ClassLikeInfo $trait, MethodInfo $method): ?DeadSymbol
    {
        $traitLower = strtolower($trait->fqcn);
        $methodLower = strtolower($method->name);
        $aliasKey = $traitLower . '::' . $methodLower;

        // Design decision #7: declaring an alias counts as usage.
        if (isset($this->aliasDeclared[$aliasKey])) {
            return null;
        }

        [$traitScopes, $classConsumers] = $this->traitReach($traitLower, $methodLower);

        $isPrivate = $method->visibility === Visibility::PrivateVis;
        $classScopes = $isPrivate
            ? $classConsumers
            : $this->familiesOf($classConsumers);

        $scopes = $traitScopes + $this->withTraitClosures($classScopes);

        return $this->verdict(
            $trait,
            $method,
            scopes: $scopes,
            callNames: [$methodLower => true] + ($this->aliasNames[$aliasKey] ?? []),
            staticTargets: [$traitLower => true] + $classScopes,
        );
    }

    /**
     * @param array<string, true> $scopes        lower scope names where a $this-> call counts
     * @param array<string, true> $callNames     lower method names that reach this method
     * @param array<string, true> $staticTargets lower class names whose static calls count
     */
    private function verdict(
        ClassLikeInfo $classLike,
        MethodInfo $method,
        array $scopes,
        array $callNames,
        array $staticTargets,
    ): ?DeadSymbol {
        foreach ($scopes as $scope => $_) {
            foreach ($callNames as $name => $__) {
                if (isset($this->onThisCalls[$scope][$name])) {
                    return null; // definite $this-> call in a valid scope
                }
            }
        }

        foreach ($staticTargets as $target => $_) {
            foreach ($callNames as $name => $__) {
                if (isset($this->staticMembers[$target][$name])) {
                    return null; // X::method(), self::, parent:: or static::
                }
            }
        }

        [$confidence, $suffix] = $this->methodConfidence($callNames, $staticTargets);

        return new DeadSymbol(
            identifier: $classLike->fqcn . '::' . $method->name,
            type: 'method',
            file: $classLike->file,
            line: $method->startLine,
            confidence: $confidence,
            reason: ucfirst($method->visibility->value) . ' method never called in its usage scope' . $suffix,
        );
    }

    /**
     * Downgrades by the strongest counter-evidence found: a same-name
     * call on an untyped receiver is the likeliest false positive, a
     * project-wide dynamic call the vaguest one.
     *
     * @param  array<string, true> $callNames
     * @param  array<string, true> $staticTargets
     * @return array{0: Confidence, 1: string}
     */
    private function methodConfidence(array $callNames, array $staticTargets): array
    {
        foreach ($callNames as $name => $_) {
            if (isset($this->namedInstanceCalls[$name])) {
                return [Confidence::Low, '; a same-name call on an unknown receiver exists'];
            }
        }

        foreach ($staticTargets as $target => $_) {
            if (isset($this->staticUnknownMember[$target])) {
                return [Confidence::Low, '; a dynamic static call targets this class'];
            }
        }

        if (($this->dynamicCounts['method-call'] ?? 0) > 0) {
            return [Confidence::Medium, '; the project contains dynamic method calls'];
        }

        return [Confidence::High, ''];
    }

    // --- functions -----------------------------------------------------------

    /** @return list<DeadSymbol> */
    private function detectDeadFunctions(ParseResult $result): array
    {
        $declared = [];

        foreach ($result->codebase->functions() as $function) {
            $declared[$this->key($function->fqn)] = true;
        }

        $called = [];

        foreach ($result->functionCalls as $call) {
            // Candidates come in PHP's resolution order (App\foo before
            // \foo): only the FIRST declared one is actually called.
            // Marking every candidate would keep a shadowed global
            // function falsely alive. When none is declared the call
            // targets a built-in or vendor function — nothing to mark.
            foreach ($call->candidates as $candidate) {
                if (isset($declared[$this->key($candidate)])) {
                    $called[$this->key($candidate)] = true;
                    break;
                }
            }
        }

        $hasDynamicCalls = ($this->dynamicCounts['function-call'] ?? 0) > 0;
        $dead = [];

        foreach ($result->codebase->functions() as $function) {
            if (isset($called[$this->key($function->fqn)])) {
                continue;
            }

            $dead[] = new DeadSymbol(
                identifier: $function->fqn,
                type: 'function',
                file: $function->file,
                line: $function->startLine,
                confidence: $hasDynamicCalls ? Confidence::Medium : Confidence::High,
                reason: 'Function never called'
                    . ($hasDynamicCalls ? '; the project contains dynamic function calls' : ''),
            );
        }

        return $dead;
    }

    // --- scope helpers -----------------------------------------------------

    /**
     * Transitive consumer closure of a trait, pruned by `insteadof`.
     *
     * @return array{0: array<string, true>, 1: array<string, true>} [trait scopes, class consumers]
     */
    private function traitReach(string $traitLower, string $methodLower): array
    {
        $traitScopes = [$traitLower => true];
        $classConsumers = [];
        $queue = [$traitLower];
        $visited = [$traitLower => true];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($this->traitUsers[$current] ?? [] as $userLower) {
                if (isset($visited[$userLower])) {
                    continue;
                }

                // This consumer resolved the method conflict AGAINST the
                // current trait: the method never reaches it.
                if (isset($this->insteadofExclusions[$userLower . '::' . $methodLower][$current])) {
                    continue;
                }

                $visited[$userLower] = true;
                $user = $this->classes[$userLower] ?? null;

                if ($user === null) {
                    continue;
                }

                if ($user->kind === ClassLikeKind::TraitType) {
                    $traitScopes[$userLower] = true;
                    $queue[] = $userLower;
                } else {
                    $classConsumers[$userLower] = true;
                }
            }
        }

        return [$traitScopes, $classConsumers];
    }

    /**
     * Ancestors + self + descendants (in-codebase only).
     *
     * @return array<string, true>
     */
    private function family(string $classLower): array
    {
        $family = [$classLower => true];

        // ancestors: single-inheritance chain
        $current = $this->classes[$classLower] ?? null;

        while ($current !== null && $current->extends !== []) {
            $parentLower = $this->key($current->extends[0]);

            if (isset($family[$parentLower])) {
                break; // inheritance cycle in broken legacy code
            }

            $family[$parentLower] = true;
            $current = $this->classes[$parentLower] ?? null;
        }

        // descendants: BFS over the children index
        $queue = [$classLower];

        while ($queue !== []) {
            foreach ($this->children[array_shift($queue)] ?? [] as $childLower) {
                if (!isset($family[$childLower])) {
                    $family[$childLower] = true;
                    $queue[] = $childLower;
                }
            }
        }

        return $family;
    }

    /**
     * @param  array<string, true> $classScopes
     * @return array<string, true>
     */
    private function familiesOf(array $classScopes): array
    {
        $result = [];

        foreach ($classScopes as $classLower => $_) {
            $result += $this->family($classLower);
        }

        return $result;
    }

    /**
     * Adds every trait consumed (transitively) by the given classes:
     * trait code runs inside the consuming class, so its $this-> calls
     * count for that class's methods.
     *
     * @param  array<string, true> $classScopes
     * @return array<string, true>
     */
    private function withTraitClosures(array $classScopes): array
    {
        $result = $classScopes;
        $queue = array_keys($classScopes);

        while ($queue !== []) {
            $current = $this->classes[array_shift($queue)] ?? null;

            foreach ($current?->traits ?? [] as $trait) {
                $traitLower = $this->key($trait);

                if (!isset($result[$traitLower])) {
                    $result[$traitLower] = true;
                    $queue[] = $traitLower;
                }
            }
        }

        return $result;
    }

    // --- misc ----------------------------------------------------------------

    // Names arrive with or without a leading `\` and in any case (PHP
    // symbols are case-insensitive): one canonical key for every index.
    private function key(string $name): string
    {
        return strtolower(ltrim($name, '\\'));
    }

    /**
     * File/line order keeps the output deterministic, so two runs over
     * the same project produce diffable JSON.
     *
     * @param  list<DeadSymbol> $symbols
     * @return list<DeadSymbol>
     */
    private function sorted(array $symbols): array
    {
        usort($symbols, static fn (DeadSymbol $a, DeadSymbol $b): int => [$a->file, $a->line] <=> [$b->file, $b->line]);

        return $symbols;
    }
}
