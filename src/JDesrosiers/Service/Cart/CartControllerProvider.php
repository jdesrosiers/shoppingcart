<?php

namespace JDesrosiers\Service\Cart;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CartControllerProvider implements ControllerProviderInterface
{
    const CONTENT_TYPE = "application/json; profile=\"/schema/cart.json\"";
    const COLLECTION_CONTENT_TYPE = "application/json; profile=\"/schema/cartCollection.json\"";

    protected $app;

    public function connect(Application $app)
    {
        $this->app = $app;

        $cart = $app["controllers_factory"];

        $cart->get("/", array($this, "listCarts"));
        $cart->get("/{cartId}", array($this, "getCart"))->bind("cart");
        $cart->post("/", array($this, "createCart"));
        $cart->post("/{cartId}/cartItems", array($this, "addItem"));
        $cart->put("/{cartId}/cartItems/{cartItemId}", array($this, "updateItem"));
        $cart->delete("/{cartId}/cartItems/{cartItemId}", array($this, "removeItem"));
        $cart->delete("/{cartId}", array($this, "deleteCart"));

        return $cart;
    }

    public function listCarts(Request $request)
    {
        return $this->app->json(
            $this->app["cart.search"]->query($request->query),
            200,
            array("Content-Type" => self::COLLECTION_CONTENT_TYPE)
        );
    }

    public function getCart($cartId)
    {
        $cart = $this->app["cart"]->fetch($cartId);
        if ($cart === false) {
            throw new NotFoundHttpException("There is no cart with cartId `$cartId`");
        }

        $json = json_encode($cart);
        $response = Response::create($json, 200, array("Content-Type" => self::CONTENT_TYPE));
        $response->setEtag(md5($json));
        
        return $response;
    }

    public function createCart(Request $request)
    {
        $cart = $this->cleanData(json_decode($request->getContent(), true));
        $cart["cartId"] = uniqid();
        $cart["createdDate"] = date(\DateTime::ISO8601);

        $this->app["cart"]->save($cart["cartId"], $cart);

        $location = $this->app["url_generator"]->generate("cart", array("cartId" => $cart["cartId"]));
        $response = $this->app->json($cart, 201, array("Content-Type" => self::CONTENT_TYPE, "Location" => $location));

        return $response;
    }

    public function addItem(Request $request, $cartId)
    {
        $item = $this->cleanData(json_decode($request->getContent(), true));
        $cart = $this->app["cart"]->fetch($cartId);

        $cartItemId = uniqid();
        $item["cartItemId"] = $cartItemId;
        $cart["cartItems"][$cartItemId] = $item;

        $this->app["cart"]->save($cart["cartId"], $cart);

        $location = $this->app["url_generator"]->generate("cart", array("cartId" => $cartId));
        return RedirectResponse::create($location, 303);
    }

    public function updateItem(Request $request, $cartId, $cartItemId)
    {
        $item = $this->cleanData(json_decode($request->getContent(), true));
        $cart = $this->app["cart"]->fetch($cartId);

        if (!array_key_exists($cartItemId, $cart["cartItems"])) {
            throw new BadRequestHttpException("The cart has no item with cartItemId [$cartItemId]");
        }

        if ($item["cartItemId"] !== $cartItemId) {
            throw new BadRequestHttpException("The cartItemId [$cartItemId] does not match the one in the request");
        }

        $cart["cartItems"][$cartItemId] = $item;

        $this->app["cart"]->save($cart["cartId"], $cart);

        $location = $this->app["url_generator"]->generate("cart", array("cartId" => $cartId));
        return RedirectResponse::create($location, 303);
    }

    public function removeItem($cartId, $cartItemId)
    {
        $cart = $this->app["cart"]->fetch($cartId);
        unset($cart["cartItems"][$cartItemId]);

        $this->app["cart"]->save($cart["cartId"], $cart);

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
        } elseif ((string) intval($value) === $value) {
            $value = intval($value);
        }

        return $value;
    }
}
