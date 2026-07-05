<?php

namespace Docs;

/**
 * Formats an amount of money for humans.
 *
 * @param double $amount Amount in euros.
 * @return string Formatted value.
 */
function format_money(float $amount): string
{
    return number_format($amount, 2) . ' €';
}

function no_doc_fn()
{
    return null;
}
