<?php

namespace JDesrosiers\Service\Cart;

use Aws\DynamoDb\Enum\Type;
use Aws\DynamoDb\Exception\DynamoDbException;
use Doctrine\Common\Cache\Cache;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class CartService implements Cache
{
    protected $dynamodb;
    protected $table;

    public function __construct($dynamodb, $environment = "")
    {
        $this->dynamodb = $dynamodb;
        $this->table = "shoppingcart" . ($environment ? "_$environment" : "") . "-cart";
    }

    public function fetch($id)
    {
        try {
            $response = $this->dynamodb->getItem(array(
                "TableName" => $this->table,
                "Key" => array(
                    "cartId" => $this->dynamodb->formatValue($id),
                ),
            ));
        } catch (DynamoDbException $e) {
            throw new ServiceUnavailableHttpException(null, $e->getMessage(), $e, $e->getCode());
        }

        if ($response["Item"] === null) {
            return false;
        }

        $item = array(
            "cartId" => $response["Item"]["cartId"][Type::STRING],
            "createdDate" => $response["Item"]["createdDate"][Type::STRING],
            "cartItems" => json_decode($response["Item"]["cartItems"][Type::STRING], true),
        );

        if (array_key_exists("completedDate", $response["Item"])) {
            $item["completedDate"] = $response["Item"]["completedDate"][Type::STRING];
        }

        return $item;
    }

    public function contains($id)
    {
        try {
            $this->fetch($id);
        } catch (NotFoundHttpException $ex) {
            return false;
        }

        return true;
    }

    public function save($id, $data, $lifeTime = 0)
    {
        $data["cartId"] = $id;
        $data["cartItems"] = json_encode($data["cartItems"]);

        try {
            $this->dynamodb->putItem(array(
                "TableName" => $this->table,
                "Item" => $this->dynamodb->formatAttributes($data),
            ));
        } catch (DynamoDbException $e) {
            throw new ServiceUnavailableHttpException(null, $e->getMessage(), $e, $e->getCode());
        }

        return true;
    }

    public function delete($id)
    {
        try {
            $this->dynamodb->deleteItem(array(
                "TableName" => $this->table,
                "Key" => array(
                    "cartId" => array(Type::STRING => $id),
                ),
            ));
        } catch (DynamoDbException $e) {
            throw new ServiceUnavailableHttpException(null, $e->getMessage(), $e, $e->getCode());
        }

        return true;
    }

    public function getStats()
    {
        return null;
    }
}
