<?php

use JDesrosiers\Service\Cart\CartControllerProvider;
use JDesrosiers\Service\Cart\CartServiceProvider;

// If ther file exists on the filesystem, bail and let that file be served.
$filename = preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file(__DIR__ . $filename)) {
    return false;
}

require_once __DIR__ . "/../vendor/autoload.php";
$app = require __DIR__ . "/../app/dev.php";

// Add controllers
$app->register(new CartServiceProvider());
$app->mount("/cart", new CartControllerProvider());

// Add CORS support
$app->after($app["cors"]);

// Handle request
$app["http_cache"]->run();
