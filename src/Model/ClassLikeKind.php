<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Model;

enum ClassLikeKind: string
{
    case ClassType = 'class';
    case InterfaceType = 'interface';
    case TraitType = 'trait';
    case EnumType = 'enum';
}
