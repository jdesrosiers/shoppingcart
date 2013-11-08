<?php

return array(
    "title" => "Generic Collection",
    "type" => "object",
    "properties" => array(
        "page" => array(
            "title" => "Current Page",
            "type" => "integer",
        ),
        "perPage" => array(
            "title" => "Per Page",
            "type" => "integer",
        ),
        "nextPage" => array(
            "title" => "Next Page",
            "type" => "integer",
        ),
        "prevPage" => array(
            "title" => "Previous Page",
            "type" => "integer",
        ),
        "filters" => array(
            "type" => "string",
        ),
    ),
    "links" => array(
        array(
            "rel" => "previous",
            "method" => "GET",
            "href" => "{?perPage}&page={prevPage}{+filters}",
        ),
        array(
            "rel" => "next",
            "method" => "GET",
            "href" => "{?perPage}&page={nextPage}{+filters}",
        ),
        array(
            "rel" => "paging",
            "method" => "GET",
            "href" => "",
            "schema" => array(
                "title" => "Paging",
                "type" => "object",
                "properties" => array(
                    "perPage" => array(
                        "title" => "Items per page",
                        "type" => "integer",
                        "minimum" => 0,
                    ),
                    "page" => array(
                        "title" => "Page",
                        "type" => "integer",
                        "minimum" => 0,
                    ),
                ),
                "additionalProperties" => false,
            ),
        ),
    ),
);
