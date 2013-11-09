<?php

return array(
    "title" => "Cart Item",
    "type" => "object",
    "properties" => array(
        "cartItemId" => array(
            "type" => "string",
            "readOnly" => true,
        ),
        "cartId" => array(
            "\$ref" => "/schema/cart.json#/properties/cartId",
        ),
        "partNumber" => array(
            "type" => "string",
            "links" => array(
                array(
                    "rel" => "full",
                    "href" => "/product/{\$}",
                ),
            ),
        ),
        "quantity" => array(
            "type" => "integer",
            "minimum" => 1,
            "default" => 1,
        ),
        "itemOptions" => array(
            "type" => "object",
        ),
    ),
    "required" => array("partNumber", "quantity"),
    "links" => array(
        array(
            "rel" => "self",
            "href" => "/cart/{cartId}/cartItems/{cartItemId}",
        ),
        array(
            "rel" => "update-item",
            "method" => "PUT",
            "href" => ""
        ),
        array(
            "rel" => "remove-item",
            "method" => "DELETE",
            "href" => ""
        ),
    ),
);
