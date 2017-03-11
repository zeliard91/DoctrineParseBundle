<?php

namespace Redking\ParseBundle\Tests\Models\Blog;

use Redking\ParseBundle\Mapping\Annotations as ORM;

/**
 * @ORM\ParseObject(collection="blog_picture")
 */
class Picture
{
    use \Redking\ParseBundle\ObjectTrait;

    /**
     * @ORM\Field(type="string")
     */
    private $file;

    /**
     * @ORM\ReferenceOne(targetDocument="Redking\ParseBundle\Tests\Models\Blog\User", inversedBy="posts")
     */
    private $user;

    public function getId()
    {
        return $this->id;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function setFile($file)
    {
        $this->file = $file;

        return $this;
    }
}