<?php

namespace App;

final class Dog extends Animal
{
    use LoggerTrait {
        log as protected writeLog;
    }

    public function makeSound(): string
    {
        return 'woof';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function intro(): string
    {
        $this->writeLog('introducing ' . $this->name);

        return parent::describe();
    }

    private function unusedInternal(): void
    {
        // Never called from anywhere: dead-code fixture for phase 3.
    }
}
