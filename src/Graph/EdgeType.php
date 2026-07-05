<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Graph;

/**
 * Every way one symbol can reference another in the dependency graph.
 */
enum EdgeType: string
{
    case Extends = 'extends';
    case Implements = 'implements';
    case UsesTrait = 'uses_trait';
    case Instantiates = 'instantiates';
    case StaticCall = 'static_call';
    case StaticProperty = 'static_property';
    case ClassConst = 'class_const';
    case ParamType = 'param_type';
    case ReturnType = 'return_type';
    case PropertyType = 'property_type';
    case CatchType = 'catch_type';
    case InstanceofCheck = 'instanceof_check';
}
