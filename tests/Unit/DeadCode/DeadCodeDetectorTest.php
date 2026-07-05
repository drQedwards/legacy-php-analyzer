<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Tests\Unit\DeadCode;

use LegacyAnalyzer\DeadCode\Confidence;
use LegacyAnalyzer\DeadCode\DeadCodeDetector;
use LegacyAnalyzer\DeadCode\DeadCodeReport;
use LegacyAnalyzer\Graph\GraphBuilder;
use LegacyAnalyzer\Parser\ProjectParser;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DeadCodeDetectorTest extends TestCase
{
    private static DeadCodeReport $simple;
    private static DeadCodeReport $dead;

    public static function setUpBeforeClass(): void
    {
        self::$simple = self::analyze('simple-project');
        self::$dead = self::analyze('dead-code-project');
    }

    private static function analyze(string $fixture): DeadCodeReport
    {
        $result = (new ProjectParser())->parse(dirname(__DIR__, 2) . '/fixtures/' . $fixture);
        $graph = (new GraphBuilder())->build($result);

        return (new DeadCodeDetector())->detect($result, $graph);
    }

    /** @return list<string> */
    private static function identifiers(array $symbols): array
    {
        $ids = array_map(static fn ($s) => $s->identifier, $symbols);
        sort($ids);

        return $ids;
    }

    // --- simple-project ----------------------------------------------------

    public function testSimpleProjectHasNoDeadClasses(): void
    {
        self::assertSame([], self::identifiers(self::$simple->classes));
    }

    public function testUnusedPrivateMethodIsDead(): void
    {
        self::assertSame(
            ['App\Dog::unusedInternal'],
            self::identifiers(self::$simple->methods),
        );
        self::assertSame(Confidence::High, self::$simple->methods[0]->confidence);
    }

    public function testTraitAliasCountsAsUsage(): void
    {
        // LoggerTrait::log is protected and only reachable through the
        // `log as protected writeLog` alias in Dog — must NOT be dead.
        self::assertNotContains('App\LoggerTrait::log', self::identifiers(self::$simple->methods));
    }

    public function testPrivateTraitMethodCalledInsideTraitIsAlive(): void
    {
        self::assertNotContains('App\LoggerTrait::formatLine', self::identifiers(self::$simple->methods));
    }

    public function testParentStaticCallKeepsProtectedMethodAlive(): void
    {
        // Animal::describe is only used via parent::describe() in Dog.
        self::assertNotContains('App\Animal::describe', self::identifiers(self::$simple->methods));
    }

    public function testUncalledFunctionsAreDead(): void
    {
        self::assertSame(
            ['App\boot', 'make_animal', 'slugify', 'unused_helper'],
            self::identifiers(self::$simple->functions),
        );
    }

    public function testNamespaceFallbackResolvesToFirstDeclaredCandidate(): void
    {
        $deadFunctions = self::identifiers(self::$simple->functions);

        // The unqualified slugify() call inside namespace App resolves
        // to App\slugify (first declared candidate) — alive.
        self::assertNotContains('App\slugify', $deadFunctions);

        // The shadowed global slugify is unreachable — dead, and with
        // High confidence: no dynamic function calls in this project.
        self::assertContains('slugify', $deadFunctions);
        $byId = array_column(self::$simple->functions, null, 'identifier');
        self::assertSame(Confidence::High, $byId['slugify']->confidence);
    }

    public function testDynamicReferenceCountsAreReported(): void
    {
        self::assertSame(1, self::$simple->dynamicReferenceCounts['new'] ?? 0);
    }

    // --- dead-code-project ---------------------------------------------------

    public function testOrphanClassAndUnreferencedInterfaceAreDead(): void
    {
        self::assertSame(
            ['Dead\Contract', 'Dead\OrphanService'],
            self::identifiers(self::$dead->classes),
        );

        $byId = array_column(self::$dead->classes, null, 'identifier');
        self::assertSame('interface', $byId['Dead\Contract']->type);
        self::assertSame('class', $byId['Dead\OrphanService']->type);
    }

    public function testExactDeadMethodList(): void
    {
        self::assertSame(
            [
                'Dead\DeepTrait::deepDead',
                'Dead\GreetB::greet',
                'Dead\UserModel::neverCalled',
            ],
            self::identifiers(self::$dead->methods),
        );
    }

    public function testMagicConstructorIsNeverACandidate(): void
    {
        // Private __construct only reached via new self() in the
        // factory: engine-invoked, excluded by name.
        self::assertNotContains('Dead\BaseModel::__construct', self::identifiers(self::$dead->methods));
    }

    public function testTraitOfTraitMethodIsAliveThroughTheChain(): void
    {
        // DeepTrait::deepHelper is called from MidTrait's code.
        self::assertNotContains('Dead\DeepTrait::deepHelper', self::identifiers(self::$dead->methods));
    }

    public function testTransitiveInheritanceCountsAsTraitConsumer(): void
    {
        // MidTrait::midHelper is only called from UserModel, which
        // inherits the trait via BaseModel.
        self::assertNotContains('Dead\MidTrait::midHelper', self::identifiers(self::$dead->methods));
    }

    public function testTemplateMethodHookIsAliveViaAncestorCall(): void
    {
        // UserModel::hookName is only dispatched from BaseModel::render().
        self::assertNotContains('Dead\UserModel::hookName', self::identifiers(self::$dead->methods));
    }

    public function testInsteadofExcludedTraitMethodIsDead(): void
    {
        $byId = array_column(self::$dead->methods, null, 'identifier');

        self::assertArrayHasKey('Dead\GreetB::greet', $byId);
        // The $this->greet() call in Greeter provably resolves to
        // GreetA::greet, so this is a HIGH confidence finding.
        self::assertSame(Confidence::High, $byId['Dead\GreetB::greet']->confidence);
        self::assertNotContains('Dead\GreetA::greet', array_keys($byId));
    }

    public function testDeadClassMethodsAreNotReportedSeparately(): void
    {
        self::assertNotContains('Dead\OrphanService::helper', self::identifiers(self::$dead->methods));
    }

    public function testInterfaceMethodsAreNeverCandidates(): void
    {
        self::assertNotContains('Dead\Contract::apply', self::identifiers(self::$dead->methods));
    }

    public function testAllDeadCodeFindingsAreHighConfidenceWithoutDynamicRefs(): void
    {
        self::assertSame([], self::$dead->dynamicReferenceCounts);

        foreach ([...self::$dead->classes, ...self::$dead->methods] as $symbol) {
            self::assertSame(Confidence::High, $symbol->confidence, $symbol->identifier);
        }
    }
}
