<?php

return array(
    "title" => "Shopping Cart",
    "type" => "object",
    "properties" => array(
        "cartId" => array(
            "type" => "string",
            "readOnly" => true,
        ),
        "createdDate" => array(
            "type" => "string",
            "readOnly" => true,
        ),
        "completedDate" => array(
            "type" => "string",
        ),
        "cartItems" => array(
            "type" => "object",
            "patternProperties" => array(
                ".*" => array(
                    "\$ref" => "/schema/cartItem.json",
                )
            ),
        ),
    ),
    "required" => array("cartItems"),
    "links" => array(
        array(
            "rel" => "self",
            "href" => "/cart/{cartId}",
        ),
        array(
            "rel" => "add-item",
            "method" => "POST",
            "href" => "/cart/{cartId}/cartItems",
            "schema" => array(
                "\$ref" => "/schema/cartItem.json",
            ),
        ),
        array(
            "rel" => "update-item",
            "method" => "PUT",
            "href" => "/cart/{cartId}/cartItems/{cartItemId}",
            "schema" => array(
                "\$ref" => "/schema/cartItem.json",
            ),
        ),
        array(
            "rel" => "remove-item",
            "method" => "DELETE",
            "href" => "/cart/{cartId}/cartItems/{cartItemId}"
        ),
        array(
            "rel" => "delete",
            "method" => "DELETE",
            "href" => "",
        ),
    ),
);
