<?php

namespace JDesrosiers\Tests\Cart;

use Doctrine\Common\Cache\ArrayCache;
use JDesrosiers\Service\Cart\CartControllerProvider;
use JDesrosiers\Service\Cart\Types\Cart;
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

        $this->app['cart']->save("4ee8e29d45851", new Cart(array(
            "cartId" => "4ee8e29d45851",
            "createdDate" => new \DateTime("2002-10-10T12:00:00-05:00"),
            "completedDate" => null,
            "cartItems" => array(
                "4d45851e8e29" => array(
                    "cartItemId" => "4d45851e8e29",
                    "product" => "/product/abc123",
                    "catalogId" => 1,
                    "quantity" => 1,
                    "price" => "1.00",
                    "itemOptions" => array(
                        "color" => "Red",
                        "size" => "XL",
                    ),
                ),
                "5851e84d4e29" => array(
                    "cartItemId" => "5851e84d4e29",
                    "product" => "/product/abc123",
                    "catalogId" => 1,
                    "quantity" => 1,
                    "price" => "1.00",
                    "itemOptions" => array(
                        "color" => "Blue",
                        "size" => "XL",
                    ),
                ),
            ),
        )));
    }

    public function tearDown()
    {
        exec("rm -rf " . dirname(__DIR__) . "/testcache/");
    }

    public function dataProviderCreateCart()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Cart>
</Cart>
XML;

        return array(
            array("application/json", '{}'),
            array("application/xml", $xml),
            array("text/xml; charset=UTF-8", $xml),
        );
    }

    /**
     * @dataProvider dataProviderCreateCart
     */
    public function testCreateCart($contentType, $requestEntity)
    {
        // TODO Variations: Accept xml, no content;
        // TODO Test with cartItems
        $headers = array(
            "HTTP_ACCEPT" => "application/json",
            "CONTENT_TYPE" => $contentType,
        );

        $client = new Client($this->app, $headers);
        $client->request("POST", "/cart/", array(), array(), $headers, $requestEntity);

        $response = $client->getResponse();
        $responseEntity = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals("application/json", $response->headers->get("Content-Type"));
        $this->assertArrayHasKey("cartId", $responseEntity);
        $this->assertGreaterThan(0, strlen($responseEntity["cartId"]));
        $this->assertEquals("/cart/{$responseEntity["cartId"]}", $response->headers->get("Location"));

        $storedCart = $this->app["cart"]->fetch($responseEntity["cartId"]);
        $this->assertEquals($responseEntity["cartId"], $storedCart->cartId);
        $this->assertEquals('DateTime', get_class($storedCart->createdDate));
    }

    public function dataProviderGetCart()
    {
        $json = '{"cartId":"4ee8e29d45851","createdDate":"2002-10-10T12:00:00-0500","cartItems":{"4d45851e8e29":{"cartItemId":"4d45851e8e29","product":"\/product\/abc123","catalogId":1,"quantity":1,"price":"1.00","itemOptions":{"color":"Red","size":"XL"}},"5851e84d4e29":{"cartItemId":"5851e84d4e29","product":"\/product\/abc123","catalogId":1,"quantity":1,"price":"1.00","itemOptions":{"color":"Blue","size":"XL"}}}}';
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Cart>
  <cartId><![CDATA[4ee8e29d45851]]></cartId>
  <createdDate><![CDATA[2002-10-10T12:00:00-0500]]></createdDate>
  <cartItems>
    <cartItem>
      <cartItemId><![CDATA[4d45851e8e29]]></cartItemId>
      <product><![CDATA[/product/abc123]]></product>
      <catalogId>1</catalogId>
      <quantity>1</quantity>
      <price><![CDATA[1.00]]></price>
      <itemOptions>
        <itemOption name="color"><![CDATA[Red]]></itemOption>
        <itemOption name="size"><![CDATA[XL]]></itemOption>
      </itemOptions>
    </cartItem>
    <cartItem>
      <cartItemId><![CDATA[5851e84d4e29]]></cartItemId>
      <product><![CDATA[/product/abc123]]></product>
      <catalogId>1</catalogId>
      <quantity>1</quantity>
      <price><![CDATA[1.00]]></price>
      <itemOptions>
        <itemOption name="color"><![CDATA[Blue]]></itemOption>
        <itemOption name="size"><![CDATA[XL]]></itemOption>
      </itemOptions>
    </cartItem>
  </cartItems>
</Cart>

XML;

        return array(
            array("application/json", "application/json", $json),
            array("application/xml", "application/xml", $xml),
            array("text/xml", "text/xml; charset=UTF-8", $xml),
        );
    }

    /**
     * @dataProvider dataProviderGetCart
     */
    public function testGetCart($accept, $expectedContentType, $expectedResponse)
    {
        $headers = array(
            "HTTP_ACCEPT" => $accept
        );

        $client = new Client($this->app, $headers);
        $client->request("GET", "/cart/4ee8e29d45851");

        $response = $client->getResponse();

        $this->assertTrue($response->isOk());
        $this->assertEquals($expectedContentType, $response->headers->get("Content-Type"));
        $this->assertEquals($expectedResponse, $response->getContent());
    }

    public function dataProviderNotAcceptableReturns406()
    {
        return array(
            array(""),
            array("text/html"),
            array("image/jpeg"),
            array("foo/bar"),
        );
    }

    /**
     * @dataProvider dataProviderNotAcceptableReturns406
     */
    public function testNotAcceptableReturns406($accept)
    {
        $headers = array(
            "HTTP_ACCEPT" => $accept
        );

        $client = new Client($this->app, $headers);
        $client->request("GET", "/cart/4ee8e29d45851");

        $response = $client->getResponse();

        $this->assertEquals("406", $response->getStatusCode());
        $this->assertEquals("", $response->getContent());
        $this->assertFalse($response->headers->has("Content-Type"));
    }

    public function testNotModifiedReturns304()
    {
        $headers = array(
            "HTTP_ACCEPT" => "application/json",
            "HTTP_IF_NONE_MATCH" => '"2cd1072ab4fe2e1a1dcd05d4731e19c5"',
        );

        $client = new Client($this->app['http_cache'], $headers);
        $client->request("GET", "/cart/4ee8e29d45851");

        $response = $client->getResponse();

        $this->assertEquals("304", $response->getStatusCode());
        $this->assertEquals("", $response->getContent());
        $this->assertFalse($response->headers->has("Content-Type"));
    }

    public function dataProviderCartNotFoundReturns404()
    {
        $json = '{"error":"Not Found","message":"Cart with ID [nosuchcart] was not found"}';
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Error>
  <error><![CDATA[Not Found]]></error>
  <message><![CDATA[Cart with ID [nosuchcart] was not found]]></message>
</Error>

XML;
        return array(
            array("application/json", "application/json", $json),
            array("application/xml", "application/xml", $xml),
            array("text/xml", "text/xml; charset=UTF-8", $xml),
        );
    }

    /**
     * @dataProvider dataProviderCartNotFoundReturns404
     */
    public function testCartNotFoundReturns404($accept, $contentType, $content)
    {
        $headers = array(
            "HTTP_ACCEPT" => $accept,
        );

        $client = new Client($this->app, $headers);
        $client->request("GET", "/cart/nosuchcart");

        $response = $client->getResponse();

        $this->assertEquals("404", $response->getStatusCode());
        $this->assertEquals($contentType, $response->headers->get("Content-Type"));
        $this->assertEquals($content, $response->getContent());
    }

    public function dataProviderAddItemToCartJson()
    {
        $jsonPost = '{"product":"\/product\/abc123","catalogId":1,"quantity":1,"price":"1.00","itemOptions":{"color":"Red","size":"XL"}}';
        $xmlPost  = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<CartItem>
  <product><![CDATA[/product/abc123]]></product>
  <catalogId>1</catalogId>
  <quantity>1</quantity>
  <price><![CDATA[1.00]]></price>
  <itemOptions>
    <itemOption name="color"><![CDATA[Red]]></itemOption>
    <itemOption name="size"><![CDATA[XL]]></itemOption>
  </itemOptions>
</CartItem>
XML;

        return array(
            array("application/json", $jsonPost),
            array("text/xml; charset=UTF-8", $xmlPost),
            array("application/xml", $xmlPost),
        );
    }

    /**
     * @dataProvider dataProviderAddItemToCartJson
     */
    public function testAddItemToCartJson($contentType, $cartItem)
    {
        // TODO Verify cart saved correctly
        $headers = array(
            "HTTP_ACCEPT" => "application/json",
            "CONTENT_TYPE" => $contentType,
        );

        $client = new Client($this->app['http_cache'], $headers);
        $client->request("POST", "/cart/4ee8e29d45851/cartItems", array(), array(), $headers, $cartItem);

        $response = $client->getResponse();
        $responseEntity = json_decode($response->getContent(), true);

        $this->assertEquals("303", $response->getStatusCode());
        $this->assertEquals("/cart/4ee8e29d45851", $response->headers->get("Location"));
        $this->assertEquals("application/json", $response->headers->get("Content-Type"));
        $this->assertArrayHasKey("cartItemId", $responseEntity);
        $this->assertGreaterThan(0, strlen($responseEntity["cartItemId"]));
    }

    public function dataProviderAddItemToCartXml()
    {
        $jsonPost = '{"product":"\/product\/abc123","catalogId":1,"quantity":1,"price":"1.00","itemOptions":{"color":"Red","size":"XL"}}';
        $xmlPost  = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<CartItem>
  <product><![CDATA[/product/abc123]]></product>
  <catalogId>1</catalogId>
  <quantity>1</quantity>
  <price><![CDATA[1.00]]></price>
  <itemOptions>
    <itemOption name="color"><![CDATA[Red]]></itemOption>
    <itemOption name="size"><![CDATA[XL]]></itemOption>
  </itemOptions>
</CartItem>
XML;

        return array(
            array("text/xml; charset=UTF-8", "application/json", $jsonPost),
            array("text/xml; charset=UTF-8", "text/xml; charset=UTF-8", $xmlPost),
            array("text/xml; charset=UTF-8", "application/xml", $xmlPost),
            array("application/xml", "application/json", $jsonPost),
            array("application/xml", "text/xml; charset=UTF-8", $xmlPost),
            array("application/xml", "application/xml", $xmlPost),
        );
    }

    /**
     * @dataProvider dataProviderAddItemToCartXml
     */
    public function testAddItemToCartXml($accept, $contentType, $cartItem)
    {
        // TODO Verify cart saved correctly
        $headers = array(
            "HTTP_ACCEPT" => $accept,
            "CONTENT_TYPE" => $contentType,
        );

        $client = new Client($this->app, $headers);
        $client->request("POST", "/cart/4ee8e29d45851/cartItems", array(), array(), $headers, $cartItem);

        $response = $client->getResponse();
        $responseEntity = simplexml_load_string($response->getContent());

        $this->assertEquals("303", $response->getStatusCode());
        $this->assertEquals("/cart/4ee8e29d45851", $response->headers->get("Location"));
        $this->assertEquals("text/xml; charset=UTF-8", $response->headers->get("Content-Type"));
        $this->assertObjectHasAttribute("cartItemId", $responseEntity);
        $this->assertGreaterThan(0, strlen((string) $responseEntity->cartItemId));
    }

    public function dataProviderUnsupportedMediaTypeReturns415()
    {
        $jsonResponse = '{"error":"Unsupported Media Type","message":""}';
        $xmlResponse = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Error>
  <error><![CDATA[Unsupported Media Type]]></error>
  <message><![CDATA[]]></message>
</Error>

XML;
        return array(
            array("application/json", "image/jpeg", "application/json", $jsonResponse),
            array("application/xml", "image/jpeg", "text/xml; charset=UTF-8", $xmlResponse),
            array("text/xml; charset=UTF-8", "image/jpeg", "text/xml; charset=UTF-8", $xmlResponse),
        );
    }

    /**
     * @dataProvider dataProviderUnsupportedMediaTypeReturns415
     */
    public function testUnsupportedMediaTypeReturns415($accept, $contentType, $expectedContentType, $expectedResponse)
    {
        $headers = array(
            "HTTP_ACCEPT" => $accept,
            "CONTENT_TYPE" => $contentType,
        );

        $client = new Client($this->app, $headers);
        $client->request("POST", "/cart/4ee8e29d45851/cartItems", array(), array(), $headers, "");

        $response = $client->getResponse();

        $this->assertEquals("415", $response->getStatusCode());
        $this->assertEquals($expectedContentType, $response->headers->get("Content-Type"));
        $this->assertEquals($expectedResponse, $response->getContent());
    }

    // Test cache invalidation
    // Test request entity validation
}
