<?php

namespace Dead;

class BaseModel
{
    use MidTrait;

    private $name;

    // Magic + private + only reachable through the static factory:
    // must NEVER be flagged dead.
    private function __construct($name)
    {
        $this->name = $name;
    }

    public static function create(string $name): self
    {
        return new self($name);
    }

    public function render(): string
    {
        // Template method: the hook is overridden in subclasses and
        // dispatched from ANCESTOR code.
        return '<p>' . $this->hookName() . '</p>';
    }

    protected function hookName(): string
    {
        return $this->name;
    }
}
