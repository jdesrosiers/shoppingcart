<?php

namespace JDesrosiers\Service\Cart;

use JDesrosiers\Json\JsonObject;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CartControllerProvider implements ControllerProviderInterface
{
    const CART_CONTENT_TYPE = "application/json; profile=/schema/cart.json";
    const CART_ITEM_CONTENT_TYPE = "application/json; profile=/schema/cart.json";
    const INDEX_CONTENT_TYPE = "application/json; profile=/schema/index.json";

    protected $app;

    public function connect(Application $app)
    {
        $this->app = $app;

        $cart = $app["controllers_factory"];

        $cart->get("/", array($this, "index"));
        $cart->get("/{cartId}", array($this, "getCart"))->bind("cart");
        $cart->post("/", array($this, "createCart"));
        $cart->post("/{cartId}/cartItems", array($this, "addItem"));
        $cart->put("/{cartId}/cartItems/{cartItemId}", array($this, "updateItem"));
        $cart->delete("/{cartId}/cartItems/{cartItemId}", array($this, "removeItem"));
        $cart->delete("/{cartId}", array($this, "deleteCart"));

        return $cart;
    }

    public function index()
    {
        $index = array(
            "title" => "shoppingcart",
            "description" => "Welcome to the shoppingcart service",
        );

        return $this->app->json($index, 200, array("Content-Type" => self::INDEX_CONTENT_TYPE));
    }

    public function getCart($cartId)
    {
        $cart = $this->app["cart"]->fetch($cartId);
        if ($cart === false) {
            throw new NotFoundHttpException("There is no cart with cartId `$cartId`");
        }

        $json = json_encode($cart->getValue());
        $response = Response::create($json, 200, array("Content-Type" => self::CART_CONTENT_TYPE));
        $response->setEtag(md5($json));
        
        return $response;
    }

    public function createCart(Request $request)
    {
        //$schemaUrl = "http://" . $request->server->get("HTTP_HOST") . "/schema/cart.json";
        $schemaUrl = __DIR__ . "/../../../../schema/cart.json";
        $cart = new JsonObject($this->cleanData(json_decode($request->getContent())), $schemaUrl);
        $cart->cartId = uniqid();
        $cart->createdDate = date(\DateTime::ISO8601);

        $this->app["cart"]->save($cart->cartId->getValue(), $cart);

        $location = $this->app["url_generator"]->generate("cart", array("cartId" => $cart->cartId->getValue()));
        $headers = array("Content-Type" => self::CART_CONTENT_TYPE, "Location" => $location);
        $response = $this->app->json($cart->getValue(), 201, $headers);

        return $response;
    }

    public function addItem(Request $request, $cartId)
    {
        //$schemaUrl = "http://" . $request->server->get("HTTP_HOST") . "/schema/cartItem.json";
        $schemaUrl = __DIR__ . "/../../../../schema/cartItem.json";
        $item = new JsonObject($this->cleanData(json_decode($request->getContent())), $schemaUrl);
        $cart = $this->app["cart"]->fetch($cartId);

        $cartItemId = uniqid();
        $item->cartItemId = $cartItemId;
        $item->cartId = $cartId;
        $cart->cartItems->$cartItemId = $item;

        $this->app["cart"]->save($cartId, $cart);

        $location = $this->app["url_generator"]->generate("cart", array("cartId" => $cartId));
        return RedirectResponse::create($location, 303);
    }

    public function updateItem(Request $request, $cartId, $cartItemId)
    {
        //$schemaUrl = "http://" . $request->server->get("HTTP_HOST") . "/schema/cartItem.json";
        $schemaUrl = __DIR__ . "/../../../../schema/cartItem.json";
        $item = new JsonObject($this->cleanData(json_decode($request->getContent())), $schemaUrl);
        $cart = $this->app["cart"]->fetch($cartId);

        if (!isset($cart->cartItems->$cartItemId)) {
            throw new BadRequestHttpException("The cart has no item with cartItemId [$cartItemId]");
        }

        if ($item->cartItemId->getValue() !== $cartItemId) {
            throw new BadRequestHttpException("The cartItemId [$cartItemId] does not match the one in the request");
        }

        $cart->cartItems->$cartItemId = $item;

        $this->app["cart"]->save($cartId, $cart);

        $location = $this->app["url_generator"]->generate("cart", array("cartId" => $cartId));
        return RedirectResponse::create($location, 303);
    }

    public function removeItem($cartId, $cartItemId)
    {
        $cart = $this->app["cart"]->fetch($cartId);
        unset($cart->cartItems->$cartItemId);

        $this->app["cart"]->save($cartId, $cart);

        $location = $this->app["url_generator"]->generate("cart", array("cartId" => $cartId));
        return RedirectResponse::create($location, 303);
    }

    public function deleteCart($cartId)
    {
        $this->app["cart"]->delete($cartId);

        return Response::create("", 204);
    }

    protected function cleanData($value)
    {
        if (is_array($value)) {
            foreach ($value as $ndx => $arrayValue) {
                $value[$ndx] = $this->cleanData($arrayValue);
            }
        } elseif (is_object($value)) {
            foreach ($value as $ndx => $property) {
                $value->$ndx = $this->cleanData($property);
            }
        } elseif ((string) intval($value) === $value) {
            $value = intval($value);
        }

        return $value;
    }
}
