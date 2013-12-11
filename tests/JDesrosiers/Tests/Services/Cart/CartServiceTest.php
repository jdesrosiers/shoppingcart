<?php

namespace JDesrosiers\Tests\Services\Cart;

use Doctrine\Common\Cache\ArrayCache;
use JDesrosiers\Json\JsonObject;
use JDesrosiers\Service\Cart\CartControllerProvider;
use Symfony\Component\HttpKernel\Client;

require_once __DIR__ . "/../../../../../vendor/autoload.php";

class CartServiceTest extends \PHPUnit_Framework_TestCase
{
    protected $app;

    public function setUp()
    {
        exec("rm -rf " . dirname(__DIR__) . "/testcache/");
        $this->app = require __DIR__ . "/../../../../../app/dev.php";

        $this->app["http_cache.cache_dir"] = dirname(__DIR__) . "/testcache/";

        $this->app["cart"] = new ArrayCache();
        $this->app->mount("/cart", new CartControllerProvider());

        $cart = array(
            "cartId" => "4ee8e29d45851",
            "createdDate" => "2002-10-10T12:00:00-05:00",
            "cartItems" => array(
                "4d45851e8e29" => array(
                    "cartItemId" => "4d45851e8e29",
                    "product" => "abc123",
                    "quantity" => 1,
                    "itemOptions" => array(
                        "color" => "Red",
                        "size" => "XL",
                    ),
                ),
                "5851e84d4e30" => array(
                    "cartItemId" => "5851e84d4e30",
                    "product" => "abc123",
                    "quantity" => 1,
                    "itemOptions" => array(
                        "color" => "Blue",
                        "size" => "XL",
                    ),
                ),
            ),
        );
        $schema = __DIR__ . "/../../../../../schema/cart.json";
        $this->app['cart']->save("4ee8e29d45851", new JsonObject(json_decode(json_encode($cart)), $schema));
    }

    public function tearDown()
    {
        exec("rm -rf " . dirname(__DIR__) . "/testcache/");
    }

    public function testCreateCart()
    {
        $headers = array(
            "HTTP_ACCEPT" => "application/json",
            "CONTENT_TYPE" => "application/json",
        );

        $client = new Client($this->app["http_cache"], $headers);
        $client->request("POST", "/cart/", array(), array(), $headers, '{"cartItems":{}}');

        $response = $client->getResponse();
        $responseEntity = json_decode($response->getContent());

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals("application/json; profile=/schema/cart.json", $response->headers->get("Content-Type"));
        $this->assertObjectHasAttribute("cartId", $responseEntity);
        $this->assertGreaterThan(0, strlen($responseEntity->cartId));
        $this->assertEquals("/cart/{$responseEntity->cartId}", $response->headers->get("Location"));

        $storedCart = $this->app["cart"]->fetch($responseEntity->cartId);
        $this->assertEquals($responseEntity->cartId, $storedCart->cartId->getValue());
        $this->assertGreaterThanOrEqual(strtotime($storedCart->createdDate->getValue()), time() + 1);
        $this->assertLessThanOrEqual(strtotime($storedCart->createdDate->getValue()), time() - 1);
    }

    public function testGetCart()
    {
        $expectedResponse = '{"cartId":"4ee8e29d45851","createdDate":"2002-10-10T12:00:00-05:00","cartItems":{"4d45851e8e29":{"cartItemId":"4d45851e8e29","product":"abc123","quantity":1,"itemOptions":{"color":"Red","size":"XL"}},"5851e84d4e30":{"cartItemId":"5851e84d4e30","product":"abc123","quantity":1,"itemOptions":{"color":"Blue","size":"XL"}}}}';

        $headers = array(
            "HTTP_ACCEPT" => "application/json",
        );

        $client = new Client($this->app["http_cache"], $headers);
        $client->request("GET", "/cart/4ee8e29d45851");

        $response = $client->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("application/json; profile=/schema/cart.json", $response->headers->get("Content-Type"));
        $this->assertJsonStringEqualsJsonString($expectedResponse, $response->getContent());
    }

    public function testNotModifiedReturns304()
    {
        $headers = array(
            "HTTP_ACCEPT" => "application/json",
            "HTTP_IF_NONE_MATCH" => '"bb0efacaa2ad144a5a0f7042e96eb00a"',
        );

        $client = new Client($this->app['http_cache'], $headers);
        $client->request("GET", "/cart/4ee8e29d45851");

        $response = $client->getResponse();

        $this->assertEquals("304", $response->getStatusCode());
        $this->assertEquals("", $response->getContent());
        $this->assertFalse($response->headers->has("Content-Type"));
    }

    public function testCartNotFoundReturns404()
    {
        $headers = array(
            "HTTP_ACCEPT" => "application/json",
        );

        $client = new Client($this->app["http_cache"], $headers);
        $client->request("GET", "/cart/nosuchcart");

        $response = $client->getResponse();

        $this->assertEquals("404", $response->getStatusCode());
    }

    public function testAddItemToCartJson()
    {
        $cartItem = '{"partNumber":"abc123","quantity":1,"itemOptions":{"color":"Red","size":"XL"}}';

        // TODO Verify cart saved correctly
        $headers = array(
            "HTTP_ACCEPT" => "application/json",
            "CONTENT_TYPE" => "application/json",
        );

        $client = new Client($this->app['http_cache'], $headers);
        $client->request("POST", "/cart/4ee8e29d45851/cartItems", array(), array(), $headers, $cartItem);

        $response = $client->getResponse();

        $this->assertEquals("303", $response->getStatusCode());
        $this->assertEquals("/cart/4ee8e29d45851", $response->headers->get("Location"));
    }

    public function testDeleteCart()
    {
        $headers = array(
            "HTTP_ACCEPT" => "application/json",
        );

        $client = new Client($this->app["http_cache"], $headers);
        $client->request("DELETE", "/cart/4ee8e29d45851");

        $response = $client->getResponse();

        $this->assertEquals("204", $response->getStatusCode());
        $this->assertEquals("application/json", $response->headers->get("Content-Type"));
        $this->assertEquals("", $response->getContent());
        $this->assertFalse($this->app["cart"]->contains("4ee8e29d45851"));
    }
}
