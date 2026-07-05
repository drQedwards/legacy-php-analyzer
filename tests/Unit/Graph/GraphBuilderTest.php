<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Tests\Unit\Graph;

use LegacyAnalyzer\Graph\DependencyGraph;
use LegacyAnalyzer\Graph\EdgeType;
use LegacyAnalyzer\Graph\GraphBuilder;
use LegacyAnalyzer\Graph\NodeKind;
use LegacyAnalyzer\Parser\ParseResult;
use LegacyAnalyzer\Parser\ProjectParser;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class GraphBuilderTest extends TestCase
{
    private static ParseResult $result;
    private static DependencyGraph $graph;

    public static function setUpBeforeClass(): void
    {
        $fixture = dirname(__DIR__, 2) . '/fixtures/simple-project';
        self::$result = (new ProjectParser())->parse($fixture);
        self::$graph = (new GraphBuilder())->build(self::$result);
    }

    public function testStructuralEdgesComeFromTheModel(): void
    {
        self::assertTrue($this->hasEdge('App\Dog', 'App\Animal', EdgeType::Extends));
        self::assertTrue($this->hasEdge('App\Animal', 'App\NamedThing', EdgeType::Implements));
        self::assertTrue($this->hasEdge('App\Dog', 'App\LoggerTrait', EdgeType::UsesTrait));
    }

    public function testExternalParentBecomesExternalNode(): void
    {
        self::assertTrue($this->hasEdge('App\NotFoundException', 'RuntimeException', EdgeType::Extends));
        self::assertSame(NodeKind::External, self::$graph->node('RuntimeException')?->kind);
    }

    public function testInternalNodeKindsMirrorClassLikeKinds(): void
    {
        self::assertSame(NodeKind::ClassNode, self::$graph->node('App\Kennel')?->kind);
        self::assertSame(NodeKind::InterfaceNode, self::$graph->node('App\NamedThing')?->kind);
        self::assertSame(NodeKind::TraitNode, self::$graph->node('App\LoggerTrait')?->kind);
    }

    public function testTypeHintEdges(): void
    {
        self::assertTrue($this->hasEdge('App\Kennel', 'App\NamedThing', EdgeType::ParamType));
        self::assertTrue(
            $this->hasEdge('App\Kennel', 'App\Dog', EdgeType::ReturnType),
            'plain return type Dog',
        );
        self::assertTrue(
            $this->hasEdge('App\Kennel', 'App\Dog', EdgeType::PropertyType),
            'nullable property type ?Dog must be unwrapped',
        );
    }

    public function testInstanceofAndCatchEdges(): void
    {
        self::assertTrue($this->hasEdge('App\Kennel', 'App\Dog', EdgeType::InstanceofCheck));
        self::assertTrue($this->hasEdge('App\Kennel', 'App\NotFoundException', EdgeType::CatchType));
    }

    public function testInstantiationEdges(): void
    {
        self::assertTrue($this->hasEdge('App\Kennel', 'App\NotFoundException', EdgeType::Instantiates));
        self::assertTrue($this->hasEdge(DependencyGraph::GLOBAL_SCOPE, 'App\Kennel', EdgeType::Instantiates));
        self::assertTrue($this->hasEdge(DependencyGraph::GLOBAL_SCOPE, 'App\Dog', EdgeType::Instantiates));
        self::assertSame(NodeKind::GlobalScope, self::$graph->node(DependencyGraph::GLOBAL_SCOPE)?->kind);
    }

    public function testSelfResolvesToEnclosingClass(): void
    {
        // NotFoundException::forName(): `new self` + `: self` return type.
        self::assertTrue(
            $this->hasEdge('App\NotFoundException', 'App\NotFoundException', EdgeType::Instantiates),
        );
        self::assertTrue(
            $this->hasEdge('App\NotFoundException', 'App\NotFoundException', EdgeType::ReturnType),
        );
    }

    public function testParentResolvesToParentClass(): void
    {
        // Dog::intro() calls parent::describe().
        $edges = self::$graph->edgesFrom('App\Dog', EdgeType::StaticCall);

        self::assertCount(1, $edges);
        self::assertSame('App\Animal', $edges[0]->to);
        self::assertSame('describe', $edges[0]->member);
    }

    public function testStaticCallCarriesMemberName(): void
    {
        $edges = self::$graph->edgesFrom('App\Kennel', EdgeType::StaticCall);

        self::assertCount(1, $edges);
        self::assertSame('App\NotFoundException', $edges[0]->to);
        self::assertSame('forName', $edges[0]->member);
    }

    public function testClassConstFetchFromGlobalScope(): void
    {
        // index.php: `Dog::class`.
        $edges = self::$graph->edgesTo('App\Dog', EdgeType::ClassConst);

        self::assertCount(1, $edges);
        self::assertSame(DependencyGraph::GLOBAL_SCOPE, $edges[0]->from);
        self::assertSame('class', $edges[0]->member);
    }

    public function testMethodCallsAreCollected(): void
    {
        $onThis = array_filter(
            self::$result->methodCalls,
            static fn ($c) => $c->onThis && $c->class === 'App\Kennel' && $c->method === 'find',
        );
        self::assertCount(1, $onThis, '$this->find(...) inside Kennel::safeFind');

        $onOther = array_filter(
            self::$result->methodCalls,
            static fn ($c) => !$c->onThis && $c->method === 'intro',
        );
        self::assertNotEmpty($onOther, '$animal->intro() has unknown receiver type');
        self::assertNull(array_values($onOther)[0]->class);
    }

    public function testFunctionCallsRecordCandidates(): void
    {
        $formatCalls = array_filter(
            self::$result->functionCalls,
            static fn ($c) => in_array('format_name', $c->candidates, true),
        );

        self::assertNotEmpty($formatCalls, 'format_name() called from index.php');
    }

    public function testDynamicNewIsReportedNotGuessed(): void
    {
        $dynamicNews = array_filter(
            self::$result->dynamicReferences,
            static fn ($d) => $d->kind === 'new',
        );

        self::assertCount(1, $dynamicNews, 'new $class in make_animal()');
        self::assertSame('function:make_animal', array_values($dynamicNews)[0]->source);
    }

    public function testEdgesToSupportsTypeFilter(): void
    {
        $all = self::$graph->edgesTo('App\Dog');
        $instanceofOnly = self::$graph->edgesTo('App\Dog', EdgeType::InstanceofCheck);

        self::assertGreaterThan(count($instanceofOnly), count($all));
        self::assertCount(1, $instanceofOnly);
    }

    private function hasEdge(string $from, string $to, EdgeType $type): bool
    {
        foreach (self::$graph->edgesFrom($from, $type) as $edge) {
            if (strcasecmp($edge->to, $to) === 0) {
                return true;
            }
        }

        return false;
    }
}
