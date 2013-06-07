<?php

namespace JDesrosiers\Silex\Provider;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\Validator\Validation;

class ValidationServiceProvider implements ServiceProviderInterface
{
    public function boot(Application $app)
    {
        AnnotationRegistry::registerAutoloadNamespace('Symfony\Component\Validator\Constraints', $app['validator.srcDir']);
    }

    public function register(Application $app)
    {
        $app['validator.builder'] = $app->share(function () use ($app) {
            $validatorBuilder = Validation::createValidatorBuilder();

            if ($app->offsetExists('validator.objectIntializers')) {
                $validatorBuilder->addObjectInitializers($app['validator.objectIntializers']);
            }

            if ($app->offsetExists('validator.xmlMappings')) {
                $validatorBuilder->addXmlMappings($app['validator.xmlMappings']);
            }

            if ($app->offsetExists('validator.yamlMappings')) {
                $validatorBuilder->addYamlMappings($app['validator.yamlMappings']);
            }

            if ($app->offsetExists('validator.methodMappings')) {
                $validatorBuilder->addMethodMappings($app['validator.methodMappings']);
            }

            if ($app->offsetExists('validator.enableAnnotationMapping')) {
                if ($app['validator.enableAnnotationMapping']) {
                    $validatorBuilder->enableAnnotationMapping();
                } else {
                    $validatorBuilder->disableAnnotationMapping();
                }
            }

            if ($app->offsetExists('validator.metadataFactory')) {
                $validatorBuilder->setMetadataFactory($app['validator.metadataFactory']);
            }

            if ($app->offsetExists('validator.metadataCache')) {
                $validatorBuilder->setMetadataCache($app['validator.metadataCache']);
            }

            if ($app->offsetExists('validator.contraintValidatorFactory')) {
                $validatorBuilder->setConstraintValidatorFactory($app['validator.contraintValidatorFactory']);
            }

            if ($app->offsetExists('validator.translator')) {
                $validatorBuilder->setTranslator($app['validator.translator']);
            }

            if ($app->offsetExists('validator.translatorDomain')) {
                $validatorBuilder->setTranslationDomain($app['validator.translatorDomain']);
            }

            return $validatorBuilder;
        });

        $app['validator'] = $app->share(function () use ($app) {
            return $app['validator.builder']->getValidator();
        });
    }

}
