<?php

namespace App;

trait LoggerTrait
{
    protected function log(string $message): void
    {
        echo $this->formatLine($message);
    }

    private function formatLine(string $message): string
    {
        return '[' . date('c') . '] ' . $message . PHP_EOL;
    }
}
