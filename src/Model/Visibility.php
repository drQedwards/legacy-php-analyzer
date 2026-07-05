<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Model;

enum Visibility: string
{
    case PublicVis = 'public';
    case ProtectedVis = 'protected';
    case PrivateVis = 'private';
}
