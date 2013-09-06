<?php

namespace JDesrosiers\Service\Cart\Types;

use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use Swagger\Annotations as SWG;

/**
 * @Serializer\XmlRoot("Cart")
 * @SWG\Model(id="Cart")
 */
class Cart
{
    /**
     * @Serializer\Type("string")
     * @Assert\Type("string")
     * @Assert\NotNull()
     * @SWG\Property(name="cartId",type="string")
     */
    protected $cartId;

    /**
     * @Serializer\Type("DateTime")
     * @Assert\DateTime()
     * @Assert\NotNull()
     * @SWG\Property(name="createdDate",type="DateTime")
     */
    protected $createdDate;

    /**
     * @Serializer\Type("DateTime")
     * @Assert\DateTime()
     * @SWG\Property(name="completedDate",type="DateTime")
     */
    protected $completedDate;

    /**
     * @Serializer\Type("array<JDesrosiers\Service\Cart\Types\CartItem>")
     * @Serializer\XmlList(inline = false, entry = "cartItem")
     * @Assert\Type("array")
     * @Assert\NotNull()
     * @SWG\Property(name="cartItems",type="Array",items="$ref:CartItem")
     */
    protected $cartItems = array();

    public function __get($name)
    {
        return $this->$name;
    }

    public function addCartItem(CartItem $cartItem)
    {
        $cartItem->setCartItemId(substr(md5(microtime(true)), 0, 12));
        $this->cartItems[$cartItem->cartItemId] = $cartItem;

        return $cartItem->cartItemId;
    }

    /**
     * @Serializer\PostDeserialize()
     */
    protected function setDefaults()
    {
        if (!$this->cartId) {
            $this->cartId = substr(md5(microtime(true)), 0, 12);
        }

        if (!($this->createdDate instanceof \DateTime)) {
            $this->createdDate = new \DateTime();
        }

//        if (array_key_exists('cartItems', $cart)) {
//            foreach ($cart['cartItems'] as $cartItemId => $cartItem) {
//                $this->cartItems[$cartItemId] = new CartItem($cartItem);
//            }
//        }
    }
}
