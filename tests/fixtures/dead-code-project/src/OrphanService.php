<?php

namespace Dead;

// Nothing references this class anywhere: dead as a whole.
class OrphanService
{
    public function run(): void
    {
    }

    private function helper(): void
    {
        // Not reported separately: the class is already dead.
    }
}
