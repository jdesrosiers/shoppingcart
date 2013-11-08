<?php

return array(
    "title" => "Cart Collection",
    "type" => "object",
    "allOf" => array(
        array(
            "\$ref" => "/schema/collection.json",
        ),
        array(
            "properties" => array(
                "collection" => array(
                    "title" => "Collection",
                    "type" => "array",
                    "items" => array(
                        "\$ref" => "/schema/cart.json",
                    ),
                    "additionalItems" => false
                ),
            ),
        ),
    ),
    "additionalProperties" => false,
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
