<?php

namespace App;

class NotFoundException extends \RuntimeException
{
    public static function forName(string $name): self
    {
        return new self("No dog called {$name}");
    }
}
