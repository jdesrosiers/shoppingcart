<?php

return array(
    "title" => "Shopping Cart",
    "type" => "object",
    "properties" => array(
        "title" => array(
            "type" => "string",
        ),
        "description" => array(
            "type" => "string",
        ),
    ),
    "links" => array(
        array(
            "rel" => "self",
            "href" => "/cart/",
        ),
        array(
            "rel" => "create",
            "method" => "POST",
            "href" => "",
            "schema" => array(
                "\$ref" => "/schema/cart.json",
            ),
        ),
    ),
);
