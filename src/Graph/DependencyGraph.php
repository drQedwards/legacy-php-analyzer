<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Graph;

/**
 * The resolved dependency graph: named nodes and typed, directed edges,
 * indexed for the queries the later phases need (who references X? what
 * does X reference?).
 *
 * Node names are case-insensitive, like PHP symbol names.
 */
final class DependencyGraph
{
    public const GLOBAL_SCOPE = '<global>';

    /** @var array<string, GraphNode> keyed by lowercased name */
    private array $nodes = [];

    /** @var list<Edge> */
    private array $edges = [];

    /** @var array<string, list<int>> lowercased source name → edge indexes */
    private array $outbound = [];

    /** @var array<string, list<int>> lowercased target name → edge indexes */
    private array $inbound = [];

    /**
     * First registration wins: declared (internal) nodes are added
     * before reference targets, so an external placeholder can never
     * overwrite a real one.
     */
    public function addNode(GraphNode $node): void
    {
        $this->nodes[strtolower($node->name)] ??= $node;
    }

    public function addEdge(Edge $edge): void
    {
        $index = count($this->edges);
        $this->edges[] = $edge;
        $this->outbound[strtolower($edge->from)][] = $index;
        $this->inbound[strtolower($edge->to)][] = $index;
    }

    public function node(string $name): ?GraphNode
    {
        return $this->nodes[strtolower(ltrim($name, '\\'))] ?? null;
    }

    /** @return list<GraphNode> */
    public function nodes(): array
    {
        return array_values($this->nodes);
    }

    /** @return list<Edge> */
    public function edges(): array
    {
        return $this->edges;
    }

    /** @return list<Edge> */
    public function edgesFrom(string $name, ?EdgeType $type = null): array
    {
        return $this->select($this->outbound[strtolower(ltrim($name, '\\'))] ?? [], $type);
    }

    /** @return list<Edge> */
    public function edgesTo(string $name, ?EdgeType $type = null): array
    {
        return $this->select($this->inbound[strtolower(ltrim($name, '\\'))] ?? [], $type);
    }

    /**
     * @param  list<int> $indexes
     * @return list<Edge>
     */
    private function select(array $indexes, ?EdgeType $type): array
    {
        $result = [];

        foreach ($indexes as $index) {
            $edge = $this->edges[$index];

            if ($type === null || $edge->type === $type) {
                $result[] = $edge;
            }
        }

        return $result;
    }
}
