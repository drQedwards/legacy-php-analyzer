<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Tests\Unit\Parser;

use LegacyAnalyzer\Model\ClassLikeKind;
use LegacyAnalyzer\Model\Codebase;
use LegacyAnalyzer\Model\Visibility;
use LegacyAnalyzer\Parser\ProjectParser;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ProjectParserTest extends TestCase
{
    private static Codebase $codebase;

    public static function setUpBeforeClass(): void
    {
        $fixture = dirname(__DIR__, 2) . '/fixtures/simple-project';
        self::$codebase = (new ProjectParser())->parse($fixture)->codebase;
    }

    public function testRejectsNonExistentDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ProjectParser())->parse(__DIR__ . '/does-not-exist');
    }

    public function testCollectsAllClassLikesExceptVendor(): void
    {
        $fqcns = array_map(
            static fn ($c) => $c->fqcn,
            self::$codebase->classLikes(),
        );
        sort($fqcns);

        self::assertSame(
            [
                'App\Animal',
                'App\Dog',
                'App\Kennel',
                'App\LoggerTrait',
                'App\NamedThing',
                'App\NotFoundException',
            ],
            $fqcns,
            'vendor/ must be excluded and Broken.php must not produce symbols',
        );
    }

    public function testBrokenFileIsRecordedAsErrorNotFatal(): void
    {
        $errors = self::$codebase->errors();

        self::assertCount(1, $errors);
        self::assertSame('broken/Broken.php', $errors[0]->file);
        self::assertStringContainsString('Syntax error', $errors[0]->message);
    }

    public function testCountsVisitedFiles(): void
    {
        // src/* (7) + helpers.php + index.php + broken/Broken.php — vendor excluded.
        self::assertSame(10, self::$codebase->fileCount());
    }

    public function testClassHierarchyIsFullyQualified(): void
    {
        $dog = self::$codebase->classLike('App\Dog');

        self::assertNotNull($dog);
        self::assertSame(ClassLikeKind::ClassType, $dog->kind);
        self::assertTrue($dog->isFinal);
        self::assertSame(['App\Animal'], $dog->extends);
        self::assertSame(['App\LoggerTrait'], $dog->traits);
    }

    public function testTraitAliasIsRecorded(): void
    {
        $dog = self::$codebase->classLike('App\Dog');
        $aliases = $dog->traitAliases;

        self::assertCount(1, $aliases);
        self::assertSame('log', $aliases[0]->method);
        self::assertSame('writeLog', $aliases[0]->alias);
        self::assertSame(Visibility::ProtectedVis, $aliases[0]->newVisibility);
    }

    public function testAbstractAndInterfaceMethodsHaveNoBody(): void
    {
        $makeSound = self::$codebase->classLike('App\Animal')->method('makeSound');
        self::assertTrue($makeSound->isAbstract);
        self::assertFalse($makeSound->hasBody);

        $getName = self::$codebase->classLike('App\NamedThing')->method('getName');
        self::assertTrue($getName->isAbstract, 'interface methods count as abstract');
        self::assertFalse($getName->hasBody);

        $describe = self::$codebase->classLike('App\Animal')->method('describe');
        self::assertTrue($describe->hasBody);
        self::assertSame(Visibility::ProtectedVis, $describe->visibility);
    }

    public function testGlobalFunctionsAreCollectedWithSignature(): void
    {
        // helpers.php (4) + App\slugify + App\boot.
        self::assertCount(6, self::$codebase->functions());
        self::assertNotNull(self::$codebase->function('App\slugify'));
        self::assertNotNull(
            self::$codebase->function('slugify'),
            'global slugify and App\slugify are distinct symbols',
        );

        $fn = self::$codebase->function('format_name');
        self::assertNotNull($fn);
        self::assertSame('string', $fn->returnType);
        self::assertSame('helpers.php', $fn->file);

        self::assertSame('name', $fn->params[0]->name);
        self::assertSame('string', $fn->params[0]->type);
        self::assertFalse($fn->params[0]->hasDefault);
        self::assertTrue($fn->params[1]->hasDefault);
    }

    public function testLookupsAreCaseInsensitive(): void
    {
        self::assertNotNull(self::$codebase->classLike('app\DOG'));
        self::assertNotNull(self::$codebase->classLike('\App\Dog'), 'leading backslash tolerated');
        self::assertNotNull(self::$codebase->function('FORMAT_NAME'));
    }

    public function testDocCommentsAreCaptured(): void
    {
        $animal = self::$codebase->classLike('App\Animal');
        self::assertStringContainsString('legacy zoo', (string) $animal->docComment);
    }
}
