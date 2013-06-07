<?php

use Doctrine\Common\Cache\FilesystemCache;
use JDesrosiers\Service\Cart\CartControllerProvider;

require_once __DIR__ . "/../vendor/autoload.php";
$app = require __DIR__ . "/../app/dev.php";

// Add controllers
$app["cart"] = $app->share(function () {
    return new FilesystemCache(__DIR__ . "/../cartstore");
});
$app->mount("/cart", new CartControllerProvider());

// Handle request
$app["http_cache"]->run();
