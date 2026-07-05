<?php

use Dead\Greeter;
use Dead\UserModel;

require_once __DIR__ . '/src/BaseModel.php';
require_once __DIR__ . '/src/UserModel.php';

$user = UserModel::create('ana');
echo $user->label();
echo $user->render();

$greeter = new Greeter();
echo $greeter->hello();
