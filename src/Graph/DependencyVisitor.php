<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Graph;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * Second AST pass: records WHAT IS USED (references to other symbols).
 * Must run after NameResolver in the same traverser.
 *
 * Unlike SymbolCollector, this visitor works in enterNode: NameResolver
 * resolves each expression's names during its own enterNode, which runs
 * before ours on the same node — so `new Foo()` is already
 * `new \App\Foo()` by the time handleNew sees it. (SymbolCollector
 * needs leaveNode because it reads CHILD nodes from the class node.)
 *
 * `self`, `static` and `parent` are NOT resolved by NameResolver (they
 * are relative to runtime context); this visitor resolves them against
 * the enclosing class. Inside a trait, `self`/`static` are attributed
 * to the trait itself (approximation: at runtime they bind to the
 * consuming class) and `parent` is unresolvable → dynamic reference.
 */
final class DependencyVisitor extends NodeVisitorAbstract
{
    /** @var list<RawReference> */
    private array $classReferences = [];

    /** @var list<MethodCallRef> */
    private array $methodCalls = [];

    /** @var list<FunctionCallRef> */
    private array $functionCalls = [];

    /** @var list<DynamicReference> */
    private array $dynamicReferences = [];

    /** @var list<array{fqcn: ?string, parent: ?string}> fqcn null = anonymous class */
    private array $classStack = [];

    /** @var list<?string> scope names for source attribution; null = anonymous class */
    private array $scopeStack = [];

    public function __construct(private readonly string $file)
    {
    }

    public function enterNode(Node $node): null
    {
        if ($node instanceof Stmt\ClassLike) {
            $this->enterClassLike($node);

            return null;
        }

        if ($node instanceof Stmt\Function_) {
            // Reference collected BEFORE pushing the scope would be
            // wrong the other way around: the return type belongs to
            // the function itself, so push first.
            $this->scopeStack[] = 'function:' . $node->namespacedName->toString();
            $this->collectTypeRefs($node->getReturnType(), EdgeType::ReturnType, $node);

            return null;
        }

        if ($node instanceof Expr\New_) {
            $this->handleNew($node);
        } elseif ($node instanceof Expr\StaticCall) {
            $this->handleStaticCall($node);
        } elseif ($node instanceof Expr\StaticPropertyFetch) {
            $this->handleStaticProperty($node);
        } elseif ($node instanceof Expr\ClassConstFetch) {
            $this->handleClassConst($node);
        } elseif ($node instanceof Expr\Instanceof_) {
            $this->handleInstanceof($node);
        } elseif ($node instanceof Stmt\Catch_) {
            $this->handleCatch($node);
        } elseif ($node instanceof Expr\FuncCall) {
            $this->handleFuncCall($node);
        } elseif ($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall) {
            $this->handleMethodCall($node);
        } elseif ($node instanceof Node\Param) {
            $this->collectTypeRefs($node->type, EdgeType::ParamType, $node);
        } elseif ($node instanceof Stmt\Property) {
            $this->collectTypeRefs($node->type, EdgeType::PropertyType, $node);
        } elseif (
            $node instanceof Stmt\ClassMethod
            || $node instanceof Expr\Closure
            || $node instanceof Expr\ArrowFunction
        ) {
            $this->collectTypeRefs($node->getReturnType(), EdgeType::ReturnType, $node);
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Stmt\ClassLike) {
            array_pop($this->classStack);
            array_pop($this->scopeStack);
        } elseif ($node instanceof Stmt\Function_) {
            array_pop($this->scopeStack);
        }

        return null;
    }

    /** @return list<RawReference> */
    public function classReferences(): array
    {
        return $this->classReferences;
    }

    /** @return list<MethodCallRef> */
    public function methodCalls(): array
    {
        return $this->methodCalls;
    }

    /** @return list<FunctionCallRef> */
    public function functionCalls(): array
    {
        return $this->functionCalls;
    }

    /** @return list<DynamicReference> */
    public function dynamicReferences(): array
    {
        return $this->dynamicReferences;
    }

    // --- node handlers ---------------------------------------------------

    private function enterClassLike(Stmt\ClassLike $node): void
    {
        $fqcn = $node->name !== null ? $node->namespacedName->toString() : null;
        $parent = $node instanceof Stmt\Class_ && $node->extends !== null
            ? $node->extends->toString()
            : null;

        // Anonymous classes have no node of their own in the graph, but
        // `new class extends X implements Y` still USES X and Y: those
        // references are attributed to the enclosing scope (emit before
        // pushing the anonymous scope).
        if ($fqcn === null && $node instanceof Stmt\Class_) {
            if ($parent !== null) {
                $this->addRef(EdgeType::Extends, $parent, $node);
            }

            foreach ($node->implements as $interface) {
                $this->addRef(EdgeType::Implements, $interface->toString(), $node);
            }
        }

        $this->classStack[] = ['fqcn' => $fqcn, 'parent' => $parent];
        $this->scopeStack[] = $fqcn;
    }

    private function handleNew(Expr\New_ $node): void
    {
        if ($node->class instanceof Stmt\Class_) {
            return; // anonymous class, handled in enterClassLike
        }

        if (!$node->class instanceof Node\Name) {
            $this->addDynamic('new', $node);

            return;
        }

        $target = $this->resolveClass($node->class);
        $target === null
            ? $this->addDynamic('new', $node)
            : $this->addRef(EdgeType::Instantiates, $target, $node);
    }

    private function handleStaticCall(Expr\StaticCall $node): void
    {
        if (!$node->class instanceof Node\Name) {
            $this->addDynamic('static-call', $node);

            return;
        }

        $target = $this->resolveClass($node->class);

        if ($target === null) {
            $this->addDynamic('static-call', $node);

            return;
        }

        // Class known but method dynamic (`X::$m()`): the edge to X is
        // still real, only the member stays null.
        $member = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
        $this->addRef(EdgeType::StaticCall, $target, $node, $member);
    }

    private function handleStaticProperty(Expr\StaticPropertyFetch $node): void
    {
        if (!$node->class instanceof Node\Name) {
            $this->addDynamic('static-property', $node);

            return;
        }

        $target = $this->resolveClass($node->class);

        if ($target === null) {
            $this->addDynamic('static-property', $node);

            return;
        }

        $member = $node->name instanceof Node\VarLikeIdentifier ? $node->name->toString() : null;
        $this->addRef(EdgeType::StaticProperty, $target, $node, $member);
    }

    private function handleClassConst(Expr\ClassConstFetch $node): void
    {
        if (!$node->class instanceof Node\Name) {
            $this->addDynamic('class-const', $node);

            return;
        }

        $target = $this->resolveClass($node->class);

        if ($target === null) {
            $this->addDynamic('class-const', $node);

            return;
        }

        $member = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
        $this->addRef(EdgeType::ClassConst, $target, $node, $member);
    }

    private function handleInstanceof(Expr\Instanceof_ $node): void
    {
        if (!$node->class instanceof Node\Name) {
            $this->addDynamic('instanceof', $node);

            return;
        }

        $target = $this->resolveClass($node->class);
        $target === null
            ? $this->addDynamic('instanceof', $node)
            : $this->addRef(EdgeType::InstanceofCheck, $target, $node);
    }

    private function handleCatch(Stmt\Catch_ $node): void
    {
        foreach ($node->types as $type) {
            $target = $this->resolveClass($type);

            if ($target !== null) {
                $this->addRef(EdgeType::CatchType, $target, $node);
            }
        }
    }

    private function handleFuncCall(Expr\FuncCall $node): void
    {
        $name = $node->name;

        if (!$name instanceof Node\Name) {
            $this->addDynamic('function-call', $node);

            return;
        }

        if ($name instanceof Node\Name\FullyQualified) {
            $candidates = [$name->toString()];
        } else {
            // Unqualified inside a namespace: NameResolver cannot decide
            // between App\foo() and \foo(), it only annotates the
            // namespaced candidate. Record both, in resolution order.
            $namespaced = $name->getAttribute('namespacedName');
            $candidates = $namespaced instanceof Node\Name
                ? [$namespaced->toString(), $name->toString()]
                : [$name->toString()];
        }

        $this->functionCalls[] = new FunctionCallRef($candidates, $this->file, $node->getStartLine());
    }

    // Without type inference the receiver of `$obj->m()` is unknown:
    // only `$this->` calls can be attributed to a class. The rest are
    // kept by name so dead-code can lower its confidence instead of
    // guessing a target.
    private function handleMethodCall(Expr\MethodCall|Expr\NullsafeMethodCall $node): void
    {
        if (!$node->name instanceof Node\Identifier) {
            $this->addDynamic('method-call', $node);

            return;
        }

        $onThis = $node->var instanceof Expr\Variable && $node->var->name === 'this';

        $this->methodCalls[] = new MethodCallRef(
            class: $onThis ? $this->currentClassFqcn() : null,
            method: $node->name->toString(),
            onThis: $onThis,
            file: $this->file,
            line: $node->getStartLine(),
        );
    }

    // --- helpers -----------------------------------------------------------

    private function collectTypeRefs(?Node $type, EdgeType $kind, Node $node): void
    {
        foreach ($this->classNamesInType($type) as $name) {
            $target = $this->resolveClass($name);

            if ($target !== null) {
                $this->addRef($kind, $target, $node);
            }
        }
    }

    /**
     * Only Node\Name is returned: scalar types (int, string, ...) are
     * Identifier nodes, so built-ins never turn into graph edges.
     *
     * @return list<Node\Name>
     */
    private function classNamesInType(?Node $type): array
    {
        if ($type instanceof Node\NullableType) {
            return $this->classNamesInType($type->type);
        }

        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            return array_merge(
                [],
                ...array_map($this->classNamesInType(...), $type->types),
            );
        }

        return $type instanceof Node\Name ? [$type] : [];
    }

    /**
     * Resolves a class name to a FQCN string, handling the relative
     * keywords. Returns null when unresolvable (parent inside a trait
     * or an anonymous-class edge case).
     */
    private function resolveClass(Node\Name $name): ?string
    {
        return match ($name->toLowerString()) {
            'self', 'static' => $this->currentClassFqcn(),
            'parent' => $this->currentClassParent(),
            default => $name->toString(),
        };
    }

    private function currentClassFqcn(): ?string
    {
        $top = end($this->classStack);

        return $top === false ? null : $top['fqcn'];
    }

    private function currentClassParent(): ?string
    {
        $top = end($this->classStack);

        return $top === false ? null : $top['parent'];
    }

    /**
     * Nearest named enclosing scope: class-like FQCN, "function:{fqn}"
     * or the global pseudo-scope. Anonymous class frames (null) are
     * skipped so their contents attribute to the surrounding code.
     */
    private function source(): string
    {
        for ($i = count($this->scopeStack) - 1; $i >= 0; --$i) {
            if ($this->scopeStack[$i] !== null) {
                return $this->scopeStack[$i];
            }
        }

        return DependencyGraph::GLOBAL_SCOPE;
    }

    private function addRef(EdgeType $kind, string $target, Node $node, ?string $member = null): void
    {
        $this->classReferences[] = new RawReference(
            source: $this->source(),
            target: $target,
            kind: $kind,
            member: $member,
            file: $this->file,
            line: $node->getStartLine(),
        );
    }

    private function addDynamic(string $kind, Node $node): void
    {
        $this->dynamicReferences[] = new DynamicReference(
            kind: $kind,
            source: $this->source(),
            file: $this->file,
            line: $node->getStartLine(),
        );
    }
}
