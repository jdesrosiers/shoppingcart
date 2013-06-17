<?php

use JDesrosiers\Service\Cart\CartControllerProvider;
use JDesrosiers\Service\Cart\CartServiceProvider;

require_once __DIR__ . "/../vendor/autoload.php";
$app = require __DIR__ . "/../app/dev.php";

// Add controllers
$app->register(new CartServiceProvider());
$app->mount("/cart", new CartControllerProvider());

// Handle request
$app["http_cache"]->run();
