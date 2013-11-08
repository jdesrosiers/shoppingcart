<?php

namespace JDesrosiers\Service\Cart;

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Iterator\ItemIterator;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class CartSearchService
{
    protected $dynamodb;
    protected $table;

    public function __construct($dynamodb, $environment = "")
    {
        $this->dynamodb = $dynamodb;
        $this->table = "shoppingcart" . ($environment ? "_$environment" : "") . "-cart";
    }

    public function query(ParameterBag $query)
    {
        try {
            $iterator = new ItemIterator($this->dynamodb->getScanIterator(array("TableName" => $this->table)));
        } catch (DynamoDbException $e) {
            throw new ServiceUnavailableHttpException(null, $e->getMessage(), $e, $e->getCode());
        }

        $page = $query->getInt("page", 0);
        $perPage = $query->getInt("perPage", 1000);

        $start = $page * $perPage;
        $end = $start + $perPage;
        $counter = 0;
        $hasMorePages = false;

        $filter = $query->all();
        unset($filter["page"]);
        unset($filter["perPage"]);

        $collection = array();
        foreach ($iterator as $item) {
            // Skip to specified page
            if ($counter < $start) {
                $counter++;
                continue;
            } elseif ($counter >= $end) {
                $hasMorePages = true;
                break;
            }

            $itemArray = $item->getAll();
            $itemArray["cartItems"] = json_decode($itemArray["cartItems"], true);

            // Apply filters
            $passFilters = true;
            foreach ($filter as $key => $value) {
                if (array_key_exists($key, $itemArray) && $itemArray[$key] !== $value) {
                    $passFilters = false;
                }
            }
            if ($passFilters === false) {
                $counter++;
                continue;
            }

            // Add item to the response
            $collection[] = $itemArray;
            $counter++;
        }

        $response = array(
            "collection" => $collection,
            "page" => $page,
            "perPage" => $perPage,
            "filters" => http_build_query($filter),
        );

        if ($page > 0) {
            $response["prevPage"] = $page - 1;
        }

        if ($hasMorePages) {
            $response["nextPage"] = $page + 1;
        }

        return $response;
    }
}
