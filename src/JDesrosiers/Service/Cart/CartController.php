<?php

namespace JDesrosiers\Service\Cart;

use Doctrine\Common\Cache\CacheProvider;
use JDesrosiers\ContentNegotiation;
use JDesrosiers\Service\Cart\Types\AddToCartResponse;
use JDesrosiers\Service\Cart\Types\Cart;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Generator\UrlGenerator;

class CartController
{
    protected $cartService;
    protected $urlGenerator;
    protected $conneg;

    public function __construct(CacheProvider $cartService, UrlGenerator $urlGenerator, ContentNegotiation $conneg)
    {
        $this->cartService = $cartService;
        $this->urlGenerator = $urlGenerator;
        $this->conneg = $conneg;
    }

    public function getCart(Cart $cart)
    {
        // Simulate a long running operation
        sleep(1);

        $response = $this->conneg->createResponse($cart);

        $response->setCache(array(
           "max_age" => 15,
           "s_maxage" => 15,
           "public" => true,
        ));

        return $response;
    }

    public function addCartItem(Request $request, Cart $cart)
    {
        $cartItem = $request->request->get("CartItem");
        $cartItemId = $cart->addCartItem($cartItem);
        $this->saveCart($cart);

        return $this->conneg->createResponse(new AddToCartResponse($cartItemId), 303, array(
           'Location' => $this->urlGenerator->generate("cart", array("cart" => $cart->cartId))
        ));
    }

    protected function saveCart(Cart $cart)
    {
        if (!$this->cartService->save($cart->cartId, $cart)) {
            throw new HttpException(500, "Failed to store cart");
        }

        return true;
    }
}
