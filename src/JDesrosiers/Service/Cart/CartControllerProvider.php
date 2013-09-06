<?php

namespace JDesrosiers\Service\Cart;

use JDesrosiers\Service\Cart\Types\Error;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Swagger\Annotations as SWG;

/**
 * @SWG\Resource(
 *     apiVersion="0.1",
 *     swaggerVersion="1.1",
 *     resourcePath="/cart",
 *     basePath="http://localhost:8000"
 * )
 */
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

        $cart->get("/", array($this, "listCarts"));
        $cart->post("/", array($this, "saveCart"));
        $cart->get("/{cartId}", array($this, "getCart"))->bind("cart");
        $cart->put("/{cartId}", array($this, "saveCart"));
        $cart->post("/{cartId}/cartItems", array($this, "addCartItem"));
        $cart->delete("/{cartId}", array($this, "deleteCart"));

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

    public function listCarts()
    {
        return $this->app["conneg"]->createResponse(array(), 200, array(
            "Content-Type" => "application/json; profile=/schema/cartCollection.json")
        );
    }

    /**
     * @SWG\Api(
     *     path="/cart/",
     *     @SWG\Operations(
     *         @SWG\Operation(httpMethod="POST", summary="Create a new cart", responseClass="Cart", nickname="CreateCart",
     *             @SWG\ErrorResponses(
     *                 @SWG\ErrorResponse(code="404", reason="Cart not found"),
     *                 @SWG\ErrorResponse(code="400", reason="Invalid input")
     *             ),
     *             @SWG\Parameters(
     *                 @SWG\Parameter(
     *                     name="cart",
     *                     description="Cart data",
     *                     paramType="body",
     *                     required="true",
     *                     allowMultiple="false",
     *                     dataType="Cart"
     *                 )
     *             )
     *         )
     *     )
     * )
     * @SWG\Api(
     *     path="/cart/{cartId}",
     *     @SWG\Operations(
     *         @SWG\Operation(httpMethod="PUT", summary="Create or update a cart", nickname="PutCart",
     *             @SWG\ErrorResponses(
     *                 @SWG\ErrorResponse(code="400", reason="Invalid input")
     *             ),
     *             @SWG\Parameters(
     *                 @SWG\Parameter(
     *                     name="cartId",
     *                     description="ID of cart that needs to be fetched",
     *                     paramType="path",
     *                     required="true",
     *                     allowMultiple="false",
     *                     dataType="string"
     *                 ),
     *                 @SWG\Parameter(
     *                     name="cart",
     *                     description="Cart data",
     *                     paramType="body",
     *                     required="true",
     *                     allowMultiple="false",
     *                     dataType="Cart"
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function saveCart($cartId = "")
    {
        $cartExists = $this->app["cart"]->contains($cartId);

        $cart = $this->app["conneg"]->deserializeRequest(__NAMESPACE__ . "\Types\Cart");
        $this->validate($cart);

        if ($this->app["cart"]->save($cart->cartId, $cart) === false) {
            throw new HttpException(500, "Failed to store cart");
        }

        $response = $this->app["conneg"]->createResponse($cart, 200, array(
            "Content-Type" => "application/json; profile=/schema/cart.json"
        ));

        if (!$cartExists) {
            $cartUrl = $this->app["url_generator"]->generate(
                "cart",
                array("cartId" => $cart->cartId),
                UrlGeneratorInterface::RELATIVE_PATH
            );

            $response->setStatusCode(201);
            $response->headers->set("Location", $cartUrl);
        }

        return $response;
    }

    /**
     * @SWG\Api(
     *     path="/cart/{cartId}",
     *     @SWG\Operations(
     *         @SWG\Operation(httpMethod="GET", summary="Find cart by ID", responseClass="Cart", nickname="GetCart",
     *             @SWG\ErrorResponses(
     *                 @SWG\ErrorResponse(code="404", reason="Cart not found")
     *             ),
     *             @SWG\Parameters(
     *                 @SWG\Parameter(
     *                     name="cartId",
     *                     description="ID of cart that needs to be fetched",
     *                     paramType="path",
     *                     required="true",
     *                     allowMultiple="false",
     *                     dataType="string"
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getCart($cartId)
    {
        $cart = $this->convertCart($cartId);

        $response = $this->app["conneg"]->createResponse($cart, 200, array(
            "Content-Type" => "application/json; profile=/schema/cart.json"
        ));

        $response->setCache(array(
            "max_age" => 15,
            "s_maxage" => 15,
            "public" => true,
        ));

        return $response;
    }

    /**
     * @SWG\Api(
     *     path="/cart/{cartId}",
     *     @SWG\Operations(
     *         @SWG\Operation(httpMethod="DELETE", summary="Delete cart by ID", nickname="DeleteCart",
     *             @SWG\ErrorResponses(
     *                 @SWG\ErrorResponse(code="404", reason="Cart not found")
     *             ),
     *             @SWG\Parameters(
     *                 @SWG\Parameter(
     *                     name="cartId",
     *                     description="ID of cart that needs to be deleted",
     *                     paramType="path",
     *                     required="true",
     *                     allowMultiple="false",
     *                     dataType="string"
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function deleteCart($cartId)
    {
        $cart = $this->convertCart($cartId);

        if ($this->app["cart"]->delete($cart->cartId) === false) {
            throw new HttpException(500, "Failed to delete cart");
        }

        return $this->app["conneg"]->createResponse("", 204);
    }
}
