<?php

declare(strict_types=1);

namespace LegacyAnalyzer\Docs;

enum DiscrepancyKind: string
{
    /** Documented type disagrees with the signature type. */
    case ParamTypeMismatch = 'param_type_mismatch';

    /** Documented parameter does not exist in the signature. */
    case ParamUnknown = 'param_unknown';

    /** Docblock claims `...$x` but the parameter is not variadic. */
    case ParamVariadicMismatch = 'param_variadic_mismatch';

    /** Documented return type disagrees with the signature. */
    case ReturnTypeMismatch = 'return_type_mismatch';

    /** {@inheritdoc} resolved, but the inherited doc no longer matches the override's real signature. */
    case InheritdocSignatureMismatch = 'inheritdoc_signature_mismatch';

    /** {@inheritdoc} but no ancestor inside the project provides a docblock. */
    case InheritdocUnresolvable = 'inheritdoc_unresolvable';
}
