<?php

namespace JDesrosiers\Service\Cart;

use JDesrosiers\Service\Cart\Types\AddToCartResponse;
use JDesrosiers\Service\Cart\Types\Cart;
use JDesrosiers\Service\Cart\Types\CreateCartResponse;
use JDesrosiers\Service\Cart\Types\Error;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CartControllerProvider implements ControllerProviderInterface
{
    protected $app;

    public function connect(Application $app)
    {
        $this->app = $app;

        $app->error(function (\Exception $e, $code) use ($app) {
            $message = $code < 500 || $app["debug"] ? $e->getMessage() : null;
            return $app["conneg"]->createResponse(new Error(Response::$statusTexts[$code], $message));
        });

        $cart = $app["controllers_factory"];

        $cart->post("/", array($this, "createCart"));
        $cart->get("/{cart}", array($this, "getCart"))->bind("cart");
        $cart->post("/{cart}/cartItems", array($this, "addCartItem"));

        $cart->convert("cart", array($this, "convertCart"));

        return $cart;
    }

    public function convertCart($cartId)
    {
        $cart = $this->app["cart"]->fetch($cartId);

        // Simulate a long running operation to test caching
        sleep(1);

        if ($cart === false) {
            throw new NotFoundHttpException("Cart with ID [$cartId] was not found");
        }

        return $cart;
    }

    protected function validate($var)
    {
        $violations = $this->app["validator"]->validate($var);

        if (count($violations)) {
            $errorMessage = array();

            foreach ($violations as $violation) {
                $errorMessage[] = 'Invalid value for "' . $violation->getPropertyPath() . '": ' . $violation->getMessage() . " [Given value: " . $violation->getInvalidValue() . "]";
            }

            throw new BadRequestHttpException(implode("\n", $errorMessage));
        }

        return true;
    }

    public function createCart(Request $request)
    {
        $cart = $this->app["conneg"]->deserializeRequest($request, __NAMESPACE__ . "\Types\Cart");
        $this->validate($cart);

        if (!$this->app["cart"]->save($cart->cartId, $cart)) {
            throw new HttpException(500, "Failed to store cart");
        }

        return $this->app["conneg"]->createResponse(new CreateCartResponse($cart->cartId), 201, array(
           'Location' => $this->app["url_generator"]->generate("cart", array("cart" => $cart->cartId))
        ));
    }

    public function getCart(Cart $cart)
    {
        $response = $this->app["conneg"]->createResponse($cart);
        $response->setCache(array(
           "max_age" => 15,
           "s_maxage" => 15,
           "public" => true,
        ));

        return $response;
    }

    public function addCartItem(Request $request, Cart $cart)
    {
        $cartItem = $this->app["conneg"]->deserializeRequest($request, __NAMESPACE__ . "\Types\CartItem");
        $this->validate($cartItem);

        $cartItemId = $cart->addCartItem($cartItem);

        if (!$this->app["cart"]->save($cart->cartId, $cart)) {
            throw new HttpException(500, "Failed to store cart");
        }

        return $this->app["conneg"]->createResponse(new AddToCartResponse($cartItemId), 303, array(
           "Location" => $this->app["url_generator"]->generate("cart", array("cart" => $cart->cartId))
        ));
    }
}
