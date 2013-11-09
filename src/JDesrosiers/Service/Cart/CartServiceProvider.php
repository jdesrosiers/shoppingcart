<?php

namespace JDesrosiers\Service\Cart;

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
                return new CartService($app["aws"]->get("dynamodb"), $app["cart.environment"]);
            }
        );
    }
}
