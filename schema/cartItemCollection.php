<?php

return array(
    "title" => "CartItem Collection",
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
                        "\$ref" => "/schema/orderItem.json",
                    ),
                    "additionalItems" => false,
                ),
            ),
        ),
    ),
    "additionalProperties" => false,
    "links" => array(
        array(
            "rel" => "self",
            "href" => "/cartItem/",
        ),
        array(
            "rel" => "create",
            "method" => "POST",
            "href" => "",
            "schema" => array(
                "\$ref" => "/schema/cartItem.json",
            ),
        ),
    ),
);
