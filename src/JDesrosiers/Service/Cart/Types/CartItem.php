<?php

namespace JDesrosiers\Service\Cart\Types;

use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @Serializer\XmlRoot("CartItem")
 */
class CartItem
{
    /**
     * @Serializer\Type("string")
     * @Assert\Type("string")
     */
    protected $cartItemId;

    /**
     * @Serializer\Type("string")
     * @Assert\Type("string")
     */
    protected $product;

    /**
     * @Serializer\Type("integer")
     * @Assert\Type("integer")
     * @Assert\Range(min = 1)
     * @Assert\NotNull()
     */
    protected $quantity;

    /**
     * @Serializer\Type("array<string,string>")
     * @Serializer\XmlMap(inline = false, entry = "itemOption", keyAttribute = "name")
     * @Assert\Type("array")
     */
    protected $itemOptions;

    public function __construct(array $cartItem)
    {
        $cartItem += array(
           'cartItemId' => null,
        );

        $this->cartItemId = $cartItem['cartItemId'];
        $this->product = $cartItem['product'];
        $this->quantity = $cartItem['quantity'];
        $this->itemOptions = $cartItem['itemOptions'];
    }

    public function __get($name)
    {
        return $this->$name;
    }

    public function setCartItemId($cartItemId)
    {
        $this->cartItemId = $cartItemId;
    }
}
