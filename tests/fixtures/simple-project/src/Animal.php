<?php

namespace App;

/**
 * Base class for every animal in this tiny legacy zoo.
 */
abstract class Animal implements NamedThing
{
    protected $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    abstract public function makeSound(): string;

    protected function describe(): string
    {
        return $this->name . ' says ' . $this->makeSound();
    }
}
