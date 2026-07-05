<?php

namespace App;

/**
 * Namespaced twin of the global slugify(): PHP's fallback rules make
 * the unqualified call below resolve HERE, shadowing the global one.
 */
function slugify(string $value): string
{
    return strtolower(str_replace(' ', '-', $value));
}

function boot(): string
{
    // Unqualified call inside namespace App → candidates in resolution
    // order: App\slugify, then \slugify. App\slugify is declared, so
    // the global \slugify is never reached from here.
    return slugify('Hello World');
}
