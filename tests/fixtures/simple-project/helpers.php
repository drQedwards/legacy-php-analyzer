<?php

/**
 * Formats a display name the legacy way.
 */
function format_name(string $name, bool $upper = false): string
{
    return $upper ? strtoupper($name) : ucfirst($name);
}

function unused_helper()
{
    return 42;
}

function make_animal(string $class)
{
    // Dynamic instantiation: unresolvable statically on purpose.
    return new $class('anon');
}

/**
 * Global twin of App\slugify. Nobody can reach it: the only slugify()
 * call sits inside namespace App and resolves to App\slugify first.
 */
function slugify($value)
{
    return strtolower(trim($value));
}
