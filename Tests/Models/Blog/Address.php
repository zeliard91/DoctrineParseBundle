<?php

namespace Redking\ParseBundle\Tests\Models\Blog;

use Redking\ParseBundle\Mapping\Annotations as ORM;

/**
 * @ORM\ParseObject(collection="blog_address")
 */
class Address
{
    use \Redking\ParseBundle\ObjectTrait;

    /**
     * @ORM\Field(type="string")
     */
    private $city;

    /**
     * @ORM\ReferenceMany(targetDocument="Redking\ParseBundle\Tests\Models\Blog\User", implementation="relation", mappedBy="addresses")
     */
    private $users;

    /**
     * @ORM\Field(type="boolean")
     */
    private $isDefault = false;

    /**
     * @ORM\Field(type="float")
     */
    private $order;

    public function __construct()
    {
        $this->users = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set city.
     *
     * @param string $city
     */
    public function setCity($city)
    {
        $this->city = $city;

        return $this;
    }

    /**
     * Get city.
     *
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * Get users
     *
     * @return \Doctrine\Common\Collections\Collection $users
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * Add user
     *
     * @param \Redking\ParseBundle\Tests\Models\Blog\User $user [description]
     */
    public function addUser(\Redking\ParseBundle\Tests\Models\Blog\User $user)
    {
        $this->users[] = $user;
    }

    /**
     * Remove user
     *
     * @param Redking\ParseBundle\Tests\Models\Blog\User $user
     */
    public function removePost(\Redking\ParseBundle\Tests\Models\Blog\User $user)
    {
        $this->users->removeElement($user);
    }

    /**
     * @return boolean
     */
    public function getIsDefault()
    {
        return $this->isDefault;
    }

    /**
     * @param boolean
     */
    public function setIsDefault($isDefault)
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    /**
     * @return float
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param float
     */
    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }
}
