<?php

return array(
    "title" => "Cart Item",
    "type" => "object",
    "properties" => array(
        "cartItemId" => array(
            "type" => "string",
            "readOnly" => true,
        ),
        "partNumber" => array(
            "type" => "string",
        ),
        "quantity" => array(
            "type" => "integer",
        ),
        "itemOptions" => array(
            "type" => "object",
        ),
    ),
    "required" => array("partNumber", "quantity"),
    "links" => array(
        array(
            "rel" => "self",
            "href" => "cartItems/{cartItemId}",
        ),
        array(
            "rel" => "get-product",
            "href" => "/product/{partNumber}",
        ),
    ),
);
