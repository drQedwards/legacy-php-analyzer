<?php

namespace Dead;

trait MidTrait
{
    use DeepTrait;

    protected function midHelper(): string
    {
        // Call attributed to MidTrait's scope, target lives in DeepTrait:
        // exercises trait-uses-trait propagation.
        return $this->deepHelper() . '!';
    }
}
