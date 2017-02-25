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

    /**
     * @ORM\ReferenceMany(targetDocument="Redking\ParseBundle\Tests\Models\Blog\Post", mappedBy="user")
     */
    private $posts;

    /**
     * @ORM\ReferenceMany(targetDocument="Redking\ParseBundle\Tests\Models\Blog\Picture", cascade="all")
     */
    private $pictures;

    public function __construct()
    {
        $this->posts = new \Doctrine\Common\Collections\ArrayCollection();
        $this->pictures = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Add post
     *
     * @param Redking\ParseBundle\Tests\Models\Blog\Post $post
     */
    public function addPost(\Redking\ParseBundle\Tests\Models\Blog\Post $post)
    {
        $this->posts[] = $post;
    }

    /**
     * Remove post
     *
     * @param Redking\ParseBundle\Tests\Models\Blog\Post $post
     */
    public function removePost(\Redking\ParseBundle\Tests\Models\Blog\Post $post)
    {
        $this->posts->removeElement($post);
    }

    /**
     * Get posts
     *
     * @return \Doctrine\Common\Collections\Collection $posts
     */
    public function getPosts()
    {
        return $this->posts;
    }

    /**
     * Add picture
     *
     * @param Redking\ParseBundle\Tests\Models\Blog\Picture $picture
     */
    public function addPicture(\Redking\ParseBundle\Tests\Models\Blog\Picture $picture)
    {
        $this->pictures[] = $picture;
    }

    /**
     * Remove picture
     *
     * @param Redking\ParseBundle\Tests\Models\Blog\Picture $picture
     */
    public function removePicture(\Redking\ParseBundle\Tests\Models\Blog\Picture $picture)
    {
        $this->pictures->removeElement($picture);
    }

    /**
     * Get pictures
     *
     * @return \Doctrine\Common\Collections\Collection $pictures
     */
    public function getPictures()
    {
        return $this->pictures;
    }
}