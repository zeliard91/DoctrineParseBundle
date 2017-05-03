<?php

namespace Redking\ParseBundle;

use Redking\ParseBundle\Mapping\Annotations as ORM;

/**
 * Common fields for all Doctrine Parse Object
 * The method getId has to be defined in the Object class.
 */
trait ObjectTrait
{
    /**
     * @var string
     * @ORM\Id
     */
    protected $id;

    /**
     * @var \DateTime
     * @ORM\Field(type="date")
     */
    protected $createdAt;

    /**
     * @var \DateTime
     * @ORM\Field(type="date")
     */
    protected $updatedAt;

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @param string \DateTime
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param string \DateTime
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}
