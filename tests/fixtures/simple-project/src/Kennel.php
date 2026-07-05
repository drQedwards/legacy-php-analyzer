<?php

namespace App;

class Kennel
{
    /** @var list<Dog> */
    private array $dogs = [];

    private ?Dog $favourite = null;

    public function add(NamedThing $animal): void
    {
        if (!$animal instanceof Dog) {
            throw new NotFoundException('This kennel only accepts dogs');
        }

        $this->dogs[] = $animal;
        $animal->intro();
    }

    public function find(string $name): Dog
    {
        foreach ($this->dogs as $dog) {
            if ($dog->getName() === $name) {
                return $dog;
            }
        }

        throw NotFoundException::forName($name);
    }

    public function safeFind(string $name): ?Dog
    {
        try {
            return $this->find($name);
        } catch (NotFoundException $e) {
            return null;
        }
    }
}
