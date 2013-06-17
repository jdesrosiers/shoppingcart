<?php

namespace JDesrosiers\Service\Cart\Types;

use JMS\Serializer\Annotation as Serializer;

/**
 * @Serializer\XmlRoot("AddToCartResponse")
 */
class CreateCartResponse
{
    /**
     * @Serializer\Type("string")
     */
    protected $cartId;

    public function __construct($cartId)
    {
        $this->cartItemId = $cartId;
    }

    public function __get($name)
    {
        return $this->$name;
    }
}