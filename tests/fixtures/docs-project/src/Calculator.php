<?php

namespace Docs;

/**
 * Arithmetic helper straight from 2009.
 */
class Calculator
{
    /**
     * Adds numbers.
     *
     * @param integer $base Starting value.
     * @param int ...$nums Numbers to add.
     * @return integer The sum.
     */
    public function add(int $base, int ...$nums): int
    {
        return $base + array_sum($nums);
    }

    /**
     * Multiplies values.
     *
     * @param int $factor
     * @param int $values Docblock forgot the ... — tolerated.
     * @return int
     */
    public function multiply(int $factor, int ...$values): int
    {
        return $factor * (int) array_product($values);
    }

    /**
     * Signature drifted, docblock did not.
     *
     * @param string $name Wrong documented type.
     * @param bool $legacyFlag This parameter no longer exists.
     * @param int ...$rest Claims variadic, the real parameter is not.
     * @return bool Wrong documented return.
     */
    public function process(int $name, int $rest): string
    {
        return $name . '-' . $rest;
    }
}
