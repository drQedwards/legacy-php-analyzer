<?php

namespace Dead;

trait GreetA
{
    protected function greet(): string
    {
        return 'hello from A';
    }
}

trait GreetB
{
    protected function greet(): string
    {
        return 'hello from B';
    }
}

class Greeter
{
    use GreetA, GreetB {
        GreetA::greet insteadof GreetB;
    }

    public function hello(): string
    {
        // Resolves to GreetA::greet — GreetB::greet is excluded by the
        // insteadof above and therefore truly dead.
        return $this->greet();
    }
}
