<?php

use JDesrosiers\Service\Cart\CartControllerProvider;
use JDesrosiers\Service\Cart\CartServiceProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

require_once __DIR__ . "/../vendor/autoload.php";
$app = require __DIR__ . "/../app/dev.php";

// Serve schema files
$app->get("/schema/{path}.json", function ($path) {
    $fullpath = __DIR__ . "/../schema/$path.json";
    if (!file_exists($fullpath)) {
        throw new NotFoundHttpException();
    }

    $schema = file_get_contents($fullpath);
    return Response::create($schema, 200, array("Content-Type" => "application/schema+json"));
})->assert("path", ".+");

// Add controllers
$app->register(new CartServiceProvider());
$app->mount("/cart", new CartControllerProvider());

// Add CORS support
$app->after($app["cors"]);

// Handle request
$app["http_cache"]->run();
