<?php

namespace Redking\ParseBundle\Tests\Models\Blog;

use Redking\ParseBundle\Mapping\Annotations as ORM;

/**
 * @ORM\ParseObject(collection="_Role")
 */
class Role
{
    use \Redking\ParseBundle\ObjectTrait;

    /**
     * @ORM\Field(type="string")
     */
    private $name;

    /**
     * @ORM\ReferenceMany(targetDocument="Redking\ParseBundle\Tests\Models\Blog\User", implementation="relation")
     */
    private $users;

    public function __construct()
    {
        $this->users = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
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
}
