<?php

namespace JDesrosiers\Service\Cart;

use Doctrine\Common\Cache\ApcCache;
use Silex\Application;
use Silex\ServiceProviderInterface;

class CartServiceProvider implements ServiceProviderInterface
{
    public function boot(Application $app)
    {

    }

    public function register(Application $app)
    {
        $app["cart.environment"] = "";

        $app["cart"] = $app->share(
            function (Application $app) {
                return new ApcCache();
            }
        );
    }
}
