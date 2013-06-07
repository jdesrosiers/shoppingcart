<?php

namespace JDesrosiers\Service\Cart\Types;

use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @Serializer\XmlRoot("Error")
 */
class Error
{
    /**
     * @Serializer\Type("string")
     * @Assert\Type("string")
     * @Assert\NotNull()
     */
    protected $error;

    /**
     * @Serializer\Type("string")
     * @Assert\Type("string")
     * @Assert\NotNull()
     */
    protected $message;

    public function __construct($error, $message)
    {
        $this->error = $error;
        $this->message = $message;
    }

    public function __get($name)
    {
        return $this->$name;
    }
}
