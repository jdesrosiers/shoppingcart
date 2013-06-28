<?php

use JDesrosiers\Silex\Provider\ContentNegotiationServiceProvider;
use JDesrosiers\Silex\Provider\CorsServiceProvider;
use JDesrosiers\Silex\Provider\JmsSerializerServiceProvider;
use JDesrosiers\Silex\Provider\ValidationServiceProvider;
use Monolog\Logger;
use Silex\Application;
use Silex\Provider\HttpCacheServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\HttpKernel\Debug\ErrorHandler;
use Symfony\Component\Validator\Mapping\Cache\ApcCache;

// Initialize application
$app = new Application();
ErrorHandler::register();
$app["debug"] = true;

// Register service providers
$app->register(new ServiceControllerServiceProvider());
$app->register(new UrlGeneratorServiceProvider());
$app->register(new JmsSerializerServiceProvider(), array(
    "serializer.srcDir" => dirname(__DIR__) . "/vendor/jms/serializer/src",
    "serializer.cacheDir" => dirname(__DIR__) . "/cache",
    "serializer.namingStrategy" => "IdenticalProperty",
));
$app->register(new ContentNegotiationServiceProvider(), array(
    "conneg.serializer" => $app["serializer"],
    "conneg.serializationFormats" => array("json", "xml", "yml"),
    "conneg.deserializationFormats" => array("json", "xml"),
    "conneg.defaultContentType" => "json",
));
$app->register(new ValidationServiceProvider(), array(
    "validator.srcDir" => dirname(__DIR__) . "/vendor/symfony/validator",
    "validator.enableAnnotationMapping" => true,
    "validator.metadataCache" => new ApcCache("mytestapp"),
));
$app->register(new MonologServiceProvider(), array(
    "monolog.logfile" => dirname(__DIR__) . "/log/development.log",
    "monolog.name" => "mytestapp",
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
    "cors.allowOrigin" => "http://petstore.swagger.wordnik.com",
));

return $app;
