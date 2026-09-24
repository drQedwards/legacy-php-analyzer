<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Graph;

use LegacyAnalyzer\Model\Codebase;
use LegacyAnalyzer\Parser\ParseResult;

/**
 * Turns a ParseResult into the final DependencyGraph:
 *
 *   1. One node per declared class-like (kind mirrors ClassLikeKind).
 *   2. Structural edges straight from the model: extends, implements,
 *      use trait (these were declarations, not expressions, so they
 *      never went through DependencyVisitor).
 *   3. One edge per collected reference; any target that is not
 *      declared in the codebase becomes an "external" node (vendor
 *      class, PHP built-in) instead of an error.
 */
final class GraphBuilder
{
    public function build(ParseResult $result): DependencyGraph
    {
        $graph = new DependencyGraph();
        $codebase = $result->codebase;

        foreach ($codebase->classLikes() as $classLike) {
            $graph->addNode(new GraphNode(
                $classLike->fqcn,
                NodeKind::from($classLike->kind->value),
            ));
        }

        foreach ($codebase->classLikes() as $classLike) {
            $structural = [
                [EdgeType::Extends, $classLike->extends],
                [EdgeType::Implements, $classLike->implements],
                [EdgeType::UsesTrait, $classLike->traits],
            ];

            foreach ($structural as [$type, $targets]) {
                foreach ($targets as $target) {
                    $this->ensureNode($graph, $target);
                    $graph->addEdge(new Edge(
                        from: $classLike->fqcn,
                        to: $target,
                        type: $type,
                        file: $classLike->file,
                        line: $classLike->startLine,
                    ));
                }
            }
        }

        foreach ($result->classReferences as $ref) {
            $this->ensureNode($graph, $ref->source);
            $this->ensureNode($graph, $ref->target);
            $graph->addEdge(new Edge(
                from: $ref->source,
                to: $ref->target,
                type: $ref->kind,
                file: $ref->file,
                line: $ref->line,
                member: $ref->member,
            ));
        }

        return $graph;
    }

    // Unknown targets become placeholder nodes, not errors: vendor and
    // built-in classes are real dependencies, just outside the project.
    private function ensureNode(DependencyGraph $graph, string $name): void
    {
        if ($graph->node($name) !== null) {
            return;
        }

        $graph->addNode(new GraphNode($name, match (true) {
            $name === DependencyGraph::GLOBAL_SCOPE => NodeKind::GlobalScope,
            str_starts_with($name, 'function:') => NodeKind::FunctionNode,
            default => NodeKind::External,
        }));
    }
}
