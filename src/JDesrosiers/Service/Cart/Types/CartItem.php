<?php

namespace JDesrosiers\Service\Cart\Types;

use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use Swagger\Annotations as SWG;

/**
 * @Serializer\XmlRoot("CartItem")
 * @SWG\Model(id="CartItem")
 */
class CartItem
{
    /**
     * @Serializer\Type("string")
     * @Assert\Type("string")
     * @SWG\Property(name="cartItemId",type="string")
     */
    protected $cartItemId;

    /**
     * @Serializer\Type("string")
     * @Assert\Type("string")
     * @SWG\Property(name="product",type="string")
     */
    protected $product;

    /**
     * @Serializer\Type("integer")
     * @Assert\Type("integer")
     * @Assert\Range(min = 1)
     * @Assert\NotNull()
     * @SWG\Property(name="quantity",type="int")
     */
    protected $quantity;

    /**
     * @Serializer\Type("array<string,string>")
     * @Serializer\XmlMap(inline = false, entry = "itemOption", keyAttribute = "name")
     * @Assert\Type("array")
     * @SWG\Property(name="itemOptions",type="Array")
     */
    protected $itemOptions;

    public function __get($name)
    {
        return $this->$name;
    }

    /**
     * @Serializer\PostDeserialize()
     */
    protected function setDefaults()
    {
        if (!$this->cartItemId) {
            $this->cartItemId = substr(md5(microtime(true)), 0, 12);
        }
    }
}
