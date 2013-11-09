<?php

use JDesrosiers\Silex\Provider\ContentNegotiationServiceProvider;
use JDesrosiers\Silex\Provider\CorsServiceProvider;
use Monolog\Logger;
use Silex\Application;
use Silex\Provider\HttpCacheServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\HttpKernel\Debug\ErrorHandler;

// Initialize application
$app = new Application();
ErrorHandler::register();
$app["debug"] = true;

// Register service providers
$app->register(new UrlGeneratorServiceProvider());
$app->register(new ContentNegotiationServiceProvider(), array(
    "conneg.responseFormats" => array("json"),
    "conneg.requestFormats" => array("json"),
    "conneg.defaultContentType" => "json",
));
$app->register(new MonologServiceProvider(), array(
    "monolog.logfile" => dirname(__DIR__) . "/log/development.log",
    "monolog.name" => "shoppingcart",
    "monolog.level" => Logger::DEBUG,
));
$app->register(new HttpCacheServiceProvider(), array(
    "http_cache.cache_dir" => dirname(__DIR__) . "/cache/",
    "http_cache.esi" => null,
    "http_cache.options" => array(
        "debug" => true,
        "allow_reload" => true,
        "allow_revalidate" => true,
    ),
));
$app->register(new CorsServiceProvider(), array(
    "cors.allowOrigin" => "http://jsonary.s3-website-us-west-2.amazonaws.com",
));

return $app;
