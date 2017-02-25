<?php

namespace Redking\ParseBundle\Tests\Models\Blog;

use Redking\ParseBundle\Mapping\Annotations as ORM;

/**
 * @ORM\ParseObject(collection="blog_user")
 */
class User
{
    use \Redking\ParseBundle\ObjectTrait;

    /**
     * @ORM\Field(type="string")
     */
    private $name;

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
}