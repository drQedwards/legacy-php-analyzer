<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Graph;

/**
 * What a graph node represents. The first four values intentionally
 * mirror ClassLikeKind so NodeKind::from($classLikeKind->value) works.
 */
enum NodeKind: string
{
    case ClassNode = 'class';
    case InterfaceNode = 'interface';
    case TraitNode = 'trait';
    case EnumNode = 'enum';
    case FunctionNode = 'function';
    case GlobalScope = 'global';
    /** Referenced but not declared in the analyzed project (vendor, PHP built-ins). */
    case External = 'external';
}
