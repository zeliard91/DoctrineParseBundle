<?php

namespace Redking\ParseBundle\Tests\Models\Blog;

use Redking\ParseBundle\Mapping\Annotations as ORM;

/**
 * @ORM\ParseObject(collection="_User")
 */
class User
{
    use \Redking\ParseBundle\ObjectTrait;

    /**
     * @ORM\Field(type="string", name="username")
     */
    private $name;

    /**
     * @ORM\Field(type="string")
     */
    private $password = 'foo';

    /**
     * @ORM\Field(type="date")
     */
    private $birthday;

    /**
     * @ORM\ReferenceMany(targetDocument="Redking\ParseBundle\Tests\Models\Blog\Post", mappedBy="user")
     */
    private $posts;

    /**
     * @ORM\ReferenceMany(targetDocument="Redking\ParseBundle\Tests\Models\Blog\Picture", cascade="all")
     */
    private $pictures;

    /**
     * @ORM\ReferenceOne(targetDocument="Redking\ParseBundle\Tests\Models\Blog\Picture")
     */
    private $avatar;

    /**
     * @ORM\ReferenceMany(targetDocument="Redking\ParseBundle\Tests\Models\Blog\Address", cascade="all", implementation="relation", inversedBy="users")
     */
    private $addresses;

    /**
     * @ORM\ReferenceMany(targetDocument="Redking\ParseBundle\Tests\Models\Blog\Picture")
     */
    private $screenshots;

    public function __construct()
    {
        $this->posts = new \Doctrine\Common\Collections\ArrayCollection();
        $this->pictures = new \Doctrine\Common\Collections\ArrayCollection();
        $this->addresses = new \Doctrine\Common\Collections\ArrayCollection();
        $this->screenshots = new \Doctrine\Common\Collections\ArrayCollection();
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

    /**
     * Set avatar
     *
     * @param Redking\ParseBundle\Tests\Models\Blog\Picture $avatar
     */
    public function setAvatar(\Redking\ParseBundle\Tests\Models\Blog\Picture $avatar)
    {
        $this->avatar = $avatar;

        return $this;
    }

    /**
     * Get avatar
     *
     * @return Redking\ParseBundle\Tests\Models\Blog\Picture $avatar
     */
    public function getAvatar()
    {
        return $this->avatar;
    }

    /**
     * Set birthday.
     *
     * @param \DateTime $birthday
     */
    public function setBirthday(\DateTime $birthday)
    {
        $this->birthday = $birthday;

        return $this;
    }

    /**
     * Get birthday.
     *
     * @return \DateTime|null
     */
    public function getBirthday()
    {
        return $this->birthday;
    }

    /**
     * Add address
     *
     * @param Redking\ParseBundle\Tests\Models\Blog\Address $address
     */
    public function addAddress(\Redking\ParseBundle\Tests\Models\Blog\Address $address)
    {
        $this->addresses[] = $address;
    }

    /**
     * Remove address
     *
     * @param Redking\ParseBundle\Tests\Models\Blog\Address $address
     */
    public function removeAddress(\Redking\ParseBundle\Tests\Models\Blog\Address $address)
    {
        $this->addresses->removeElement($address);
    }

    /**
     * Get addresses
     *
     * @return \Doctrine\Common\Collections\Collection $addresses
     */
    public function getAddresses()
    {
        return $this->addresses;
    }

    /**
     * Get address by city.
     *
     * @param  string $city
     * @return Address|null
     */
    public function getAddressByCity($city)
    {
        foreach ($this->addresses as $address) {
            if ($address->getCity() === $city) {
                return $address;
            }
        }

        return null;
    }

    /**
     * Add screenshot
     *
     * @param Redking\ParseBundle\Tests\Models\Blog\Picture $screenshot
     */
    public function addScreenshot(\Redking\ParseBundle\Tests\Models\Blog\Picture $screenshot)
    {
        $this->screenshots[] = $screenshot;
    }

    /**
     * Remove screenshot
     *
     * @param Redking\ParseBundle\Tests\Models\Blog\Picture $screenshot
     */
    public function removeScreenshot(\Redking\ParseBundle\Tests\Models\Blog\Picture $screenshot)
    {
        $this->screenshots->removeElement($screenshot);
    }

    /**
     * Get screenshots
     *
     * @return \Doctrine\Common\Collections\Collection $screenshots
     */
    public function getScreenshots()
    {
        return $this->screenshots;
    }
}
