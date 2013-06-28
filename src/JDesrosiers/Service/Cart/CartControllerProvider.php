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
        $cart->get("/{cartId}", array($this, "getCart"))->bind("cart");
        $cart->put("/{cartId}", array($this, "putCart"));
        $cart->post("/{cartId}/cartItems", array($this, "addCartItem"));
        $cart->delete("/{cartId}", array($this, "deleteCart"));

        $cart->after($app["cors"]);

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
        $requestData = $this->app["conneg"]->deserializeRequest($request, "array");
        $cart = new Cart($requestData);
        $this->validate($cart);

        if ($this->app["cart"]->save($cart->cartId, $cart) === false) {
            throw new HttpException(500, "Failed to store cart");
        }

        return $this->app["conneg"]->createResponse(new CreateCartResponse($cart->cartId), 201, array(
            'Location' => $this->app["url_generator"]->generate("cart", array("cartId" => $cart->cartId))
        ));
    }

    public function getCart($cartId)
    {
        $cart = $this->convertCart($cartId);

        $response = $this->app["conneg"]->createResponse($cart);
        $response->setCache(array(
            "max_age" => 15,
            "s_maxage" => 15,
            "public" => true,
        ));

        return $response;
    }

    public function putCart(Request $request, $cartId)
    {
        $cart = $this->app["conneg"]->deserializeRequest($request, __NAMESPACE__ . "\Types\Cart");
        $this->validate($cart);
        
        $cartExists = $this->app["cart"]->contains($cartId);

        if ($this->app["cart"]->save($cart->cartId, $cart) === false) {
            throw new HttpException(500, "Failed to store cart");
        }

        if ($cartExists) {
            return new Response("", 204);
        } else {
            return new Response("", 201, array(
                'Location' => $this->app["url_generator"]->generate("cart", array("cartId" => $cart->cartId)),
            ));
        }
    }

    public function addCartItem(Request $request, $cartId)
    {
        $cart = $this->convertCart($cartId);

        $cartItem = $this->app["conneg"]->deserializeRequest($request, __NAMESPACE__ . "\Types\CartItem");
        $this->validate($cartItem);

        $cartItemId = $cart->addCartItem($cartItem);

        if ($this->app["cart"]->save($cart->cartId, $cart) === false) {
            throw new HttpException(500, "Failed to store cart");
        }

        return $this->app["conneg"]->createResponse(new AddToCartResponse($cartItemId), 303, array(
            "Location" => $this->app["url_generator"]->generate("cart", array("cartId" => $cart->cartId))
        ));
    }

    public function deleteCart($cartId)
    {
        $cart = $this->convertCart($cartId);

        if ($this->app["cart"]->delete($cart->cartId) === false) {
            throw new HttpException(500, "Failed to store cart");
        }

        return $this->app["conneg"]->createResponse("", 204);
    }
}
