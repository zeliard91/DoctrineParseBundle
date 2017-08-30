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
}
