<?php

namespace JDesrosiers\Service\Cart;

use JDesrosiers\Service\Cart\Types\Error;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CartControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $app["cart.controller"] = function() use ($app) {
            return new CartController($app["cart"], $app["url_generator"], $app["conneg"]);
        };

        $app->error(function (\Exception $e, $code) use ($app) {
            $message = $code < 500 || $app["debug"] ? $e->getMessage() : null;
            return $app["conneg"]->createResponse(new Error(Response::$statusTexts[$code], $message));
        });

        $cart = $app["controllers_factory"];
        $cart->get("/{cart}", "cart.controller:getCart")->bind("cart");
        $cart->post("/{cart}/cartItems", "cart.controller:addCartItem")
            ->before(function (Request $request) use ($app) {
                $cartItem = $app["conneg"]->deserializeRequest($request, __NAMESPACE__ . "\Types\CartItem");
                $violations = $app["validator"]->validate($cartItem);

                if (count($violations)) {
                    $errorMessage = array();

                    foreach ($violations as $violation) {
                        $errorMessage[] = 'Invalid value for "' . $violation->getPropertyPath() . '": ' . $violation->getMessage() . " [Given value: " . $violation->getInvalidValue() . "]";
                    }

                    throw new BadRequestHttpException(implode("\n", $errorMessage));
                }

                $request->request->set('CartItem', $cartItem);
            });

        $cart->convert("cart", function ($cartId) use ($app) {
            $cart = $app["cart"]->fetch($cartId);

            if ($cart === false) {
                throw new NotFoundHttpException("Cart with ID [$cartId] was not found");
            }
            
            return $cart;
        });

        return $cart;
    }
}
