<?php

namespace Redking\ParseBundle\Tests\Models;

use Redking\ParseBundle\Mapping\Annotations as ORM;

/**
 * Redking\ParseBundle\Tests\Models\TestGeneration
 */
#[ORM\ParseObject(collection: "TestGeneration")]
class TestGeneration
{
    use \Redking\ParseBundle\ACLTrait;

    /**
     * @var string $id
     */
    #[ORM\Id()]
    protected $id;

    /**
     * @var \DateTime $createdAt
     */
    #[ORM\Field(type:"date")]
    protected $createdAt;

    /**
     * @var \DateTime $updatedAt
     */
    #[ORM\Field(type:"date")]
    protected $updatedAt;

    /**
     * @var string $name
     */
    #[ORM\Field(name:"label", type:"string")]
    protected $name;

    /**
     * @var \Parse\ParseGeoPoint $location
     */
    #[ORM\Field(type:"geopoint")]
    protected $location;

    /**
     * @var bool $isActive
     */
    #[ORM\Field(type:"boolean")]
    protected $isActive = false;

    /**
     * @var \Parse\ParseFile $backup
     */
    #[ORM\Field(type:"file")]
    protected $backup;

    /**
     * @var \Doctrine\Common\Collections\Collection<int, \Redking\ParseBundle\Tests\Models\Blog\Picture>
     */
    #[ORM\ReferenceMany(targetDocument:\Redking\ParseBundle\Tests\Models\Blog\Picture::class, cascade:["persist"])]
    protected $pictures;

    /**
     * @var \Redking\ParseBundle\Tests\Models\Blog\User
     */
    #[ORM\ReferenceOne(targetDocument:\Redking\ParseBundle\Tests\Models\Blog\User::class)]
    protected $owner;

    public function __construct()
    {
        $this->pictures = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
    public function __toString()
    {
        return $this->name."";
    }
    
    public function getId(): null|string 
    {
        return $this->id;
    }

    public function setCreatedAt(\DateTime $createdAt = null): self
    {
        $this->createdAt = $createdAt;
    
        return $this;
    }

    public function getCreatedAt(): null|\DateTime 
    {
        return $this->createdAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt = null): self
    {
        $this->updatedAt = $updatedAt;
    
        return $this;
    }

    public function getUpdatedAt(): null|\DateTime 
    {
        return $this->updatedAt;
    }

    public function setName(string|\BackedEnum $name = null): self
    {
        $this->name = $name;
    
        return $this;
    }

    public function getName(): null|string|\BackedEnum 
    {
        return $this->name;
    }

    public function setLocation(\Parse\ParseGeoPoint $location = null): self
    {
        $this->location = $location;
    
        return $this;
    }

    public function getLocation(): null|\Parse\ParseGeoPoint 
    {
        return $this->location;
    }

    public function setIsActive(bool $isActive = null): self
    {
        $this->isActive = $isActive;
    
        return $this;
    }

    public function getIsActive(): null|bool 
    {
        return $this->isActive;
    }

    public function setBackup(\Parse\ParseFile $backup = null): self
    {
        $this->backup = $backup;
    
        return $this;
    }

    public function getBackup(): null|\Parse\ParseFile 
    {
        return $this->backup;
    }

    public function addPicture(\Redking\ParseBundle\Tests\Models\Blog\Picture $picture): self
    {
        $this->pictures[] = $picture;
    
        return $this;
    }

    public function removePicture(\Redking\ParseBundle\Tests\Models\Blog\Picture $picture): self
    {
        $this->pictures->removeElement($picture);
    
        return $this;
    }

    /**
     * @return \Doctrine\Common\Collections\Collection<int, \Redking\ParseBundle\Tests\Models\Blog\Picture> 
     */
    public function getPictures(): \Doctrine\Common\Collections\Collection 
    {
        return $this->pictures;
    }

    public function setOwner(\Redking\ParseBundle\Tests\Models\Blog\User $owner): self
    {
        $this->owner = $owner;
    
        return $this;
    }

    public function getOwner(): null|\Redking\ParseBundle\Tests\Models\Blog\User 
    {
        return $this->owner;
    }
}