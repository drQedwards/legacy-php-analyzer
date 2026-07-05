<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Tests\Unit\Docs;

use LegacyAnalyzer\Docs\ClassDoc;
use LegacyAnalyzer\Docs\DiscrepancyKind;
use LegacyAnalyzer\Docs\DocGenerator;
use LegacyAnalyzer\Docs\DocReport;
use LegacyAnalyzer\Docs\MethodDoc;
use LegacyAnalyzer\Parser\ProjectParser;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DocGeneratorTest extends TestCase
{
    private static DocReport $report;

    public static function setUpBeforeClass(): void
    {
        $fixture = dirname(__DIR__, 2) . '/fixtures/docs-project';
        $result = (new ProjectParser())->parse($fixture);
        self::$report = (new DocGenerator())->generate($result->codebase);
    }

    private static function classDoc(string $fqcn): ClassDoc
    {
        foreach (self::$report->classes as $class) {
            if (strcasecmp($class->fqcn, $fqcn) === 0) {
                return $class;
            }
        }

        self::fail("Class {$fqcn} not found in the report");
    }

    private static function methodDoc(string $fqcn, string $method): MethodDoc
    {
        foreach (self::classDoc($fqcn)->methods as $methodDoc) {
            if (strcasecmp($methodDoc->name, $method) === 0) {
                return $methodDoc;
            }
        }

        self::fail("Method {$fqcn}::{$method} not found in the report");
    }

    /** @return list<array{0: string, 1: string}> [location, kind] */
    private static function discrepancyPairs(): array
    {
        return array_map(
            static fn ($d) => [$d->location, $d->kind->value],
            self::$report->discrepancies,
        );
    }

    // --- normalization: NO false positives ---------------------------------

    public function testNullableStyleDifferencesAreNotDiscrepancies(): void
    {
        // ?string signature vs "string|null" docblock (setLabel) and
        // ?object vs "object|null" (Repository::find).
        foreach (self::discrepancyPairs() as [$location, $_]) {
            self::assertStringNotContainsString('Box::setLabel', $location);
            self::assertStringNotContainsString('Docs\Repository::', $location);
        }
    }

    public function testLegacyAliasesAndProperVariadicDocAreAccepted(): void
    {
        // integer/int aliases + correctly documented ...$nums.
        foreach (self::discrepancyPairs() as [$location, $_]) {
            self::assertStringNotContainsString('Calculator::add', $location);
        }
    }

    public function testDocMissingVariadicEllipsisIsTolerated(): void
    {
        foreach (self::discrepancyPairs() as [$location, $_]) {
            self::assertStringNotContainsString('Calculator::multiply', $location);
        }
    }

    public function testArrayRefinementAndThisStaticAreAccepted(): void
    {
        // Item[] vs array, @return $this vs : static.
        foreach (self::discrepancyPairs() as [$location, $_]) {
            self::assertStringNotContainsString('Box::setItems', $location);
        }
    }

    public function testDoubleAliasInFunctionDocIsAccepted(): void
    {
        foreach (self::discrepancyPairs() as [$location, $_]) {
            self::assertStringNotContainsString('format_money', $location);
        }
    }

    // --- real discrepancies -------------------------------------------------

    public function testDriftedMethodProducesAllFourKinds(): void
    {
        $kinds = [];

        foreach (self::$report->discrepancies as $d) {
            if ($d->location === 'Docs\Calculator::process') {
                $kinds[] = $d->kind;
            }
        }

        self::assertCount(4, $kinds);
        self::assertContains(DiscrepancyKind::ParamTypeMismatch, $kinds);
        self::assertContains(DiscrepancyKind::ParamUnknown, $kinds);
        self::assertContains(DiscrepancyKind::ParamVariadicMismatch, $kinds);
        self::assertContains(DiscrepancyKind::ReturnTypeMismatch, $kinds);
    }

    public function testInheritdocWithMatchingOverrideIsClean(): void
    {
        self::assertNotContains(
            'Docs\UserRepository::find',
            array_column(self::discrepancyPairs(), 0),
        );

        $method = self::methodDoc('Docs\UserRepository', 'find');
        self::assertSame('Docs\Repository', $method->inheritedFrom);
        self::assertSame('Finds an entity by its identifier.', $method->summary);
    }

    public function testInheritdocAgainstDriftedOverrideIsFlagged(): void
    {
        $matches = array_filter(
            self::$report->discrepancies,
            static fn ($d) => $d->location === 'Docs\LegacyUserRepository::find',
        );

        self::assertCount(1, $matches, 'only $id drifted; the undocumented $cached is not a discrepancy');
        $found = array_values($matches)[0];
        self::assertSame(DiscrepancyKind::InheritdocSignatureMismatch, $found->kind);
        self::assertStringContainsString('inherited from Docs\Repository', $found->detail);
        self::assertStringContainsString('$id', $found->detail);
    }

    public function testInheritdocFromExternalAncestorIsUnresolvable(): void
    {
        self::assertContains(
            ['Docs\ExternalChild::count', DiscrepancyKind::InheritdocUnresolvable->value],
            self::discrepancyPairs(),
        );
    }

    public function testTotalDiscrepancyCountIsExact(): void
    {
        // process (4) + LegacyUserRepository::find (1) + ExternalChild::count (1)
        self::assertCount(6, self::$report->discrepancies);
    }

    // --- generated documentation --------------------------------------------

    public function testSummaryIsFirstParagraphOnly(): void
    {
        $method = self::methodDoc('Docs\Repository', 'find');

        self::assertSame('Finds an entity by its identifier.', $method->summary);
        self::assertSame('The identifier.', $method->params[0]->description);
        self::assertSame('The entity or null when missing.', $method->returnDescription);
    }

    public function testSignatureRendering(): void
    {
        self::assertSame(
            'add(int $base, int ...$nums): int',
            self::methodDoc('Docs\Calculator', 'add')->signature,
        );
        self::assertSame(
            'find(int $id, bool $cached = …): ?object',
            self::methodDoc('Docs\LegacyUserRepository', 'find')->signature,
        );
    }

    public function testCoverageCounts(): void
    {
        $coverage = self::$report->coverage;

        // Repository, UserRepository, LegacyUserRepository, ExternalChild,
        // Calculator, Box, Item, NoDocs.
        self::assertSame(8, $coverage->classLikesTotal);
        // Class-level docblocks: Repository + Calculator.
        self::assertSame(2, $coverage->classLikesDocumented);
        // 3×find + count + add + multiply + process + setItems + setLabel + plain.
        self::assertSame(10, $coverage->methodsTotal);
        self::assertSame(9, $coverage->methodsDocumented, 'only NoDocs::plain lacks a docblock');
        self::assertSame(2, $coverage->functionsTotal);
        self::assertSame(1, $coverage->functionsDocumented);
    }

    public function testUndocumentedSymbolsStillAppearInTheDocs(): void
    {
        $method = self::methodDoc('Docs\NoDocs', 'plain');

        self::assertFalse($method->documented);
        self::assertNull($method->summary);
        self::assertSame('plain($anything)', $method->signature);
    }
}
