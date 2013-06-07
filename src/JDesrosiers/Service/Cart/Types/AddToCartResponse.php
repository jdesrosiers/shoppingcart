<?php

namespace JDesrosiers\Service\Cart\Types;

use JMS\Serializer\Annotation as Serializer;

/**
 * @Serializer\XmlRoot("AddToCartResponse")
 */
class AddToCartResponse
{
    /**
     * @Serializer\Type("string")
     */
    protected $cartItemId;

    public function __construct($cartItemId)
    {
        $this->cartItemId = $cartItemId;
    }

    public function __get($name)
    {
        return $this->$name;
    }
}