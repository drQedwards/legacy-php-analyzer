<?php

namespace Dead;

trait DeepTrait
{
    protected function deepHelper(): string
    {
        return 'deep';
    }

    private function deepDead(): string
    {
        return 'never reached';
    }
}
