<?php

// Legacy-style procedural entry point, global scope on purpose.

use App\Dog;
use App\Kennel;

require_once __DIR__ . '/src/Animal.php';
require_once __DIR__ . '/src/Dog.php';
require_once __DIR__ . '/helpers.php';

$kennel = new Kennel();
$kennel->add(new Dog(format_name('rex')));

echo 'Handled by ' . Dog::class . PHP_EOL;
