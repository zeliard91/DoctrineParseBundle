<?php

namespace Redking\ParseBundle\Tests\Models\Blog;

use Redking\ParseBundle\Mapping\Annotations as ORM;

/**
 * Model that declares $id as protected directly in the class (not via ObjectTrait).
 * Used to test that executeDeletions() can set $id to null after deletion.
 */
#[ORM\ParseObject(collection: "blog_protected_id_model")]
class ProtectedIdModel
{
    use \Redking\ParseBundle\ACLTrait;

    #[ORM\Id()]
    protected $id;

    #[ORM\Field(type: "date")]
    protected $createdAt;

    #[ORM\Field(type: "date")]
    protected $updatedAt;

    #[ORM\Field(type: "string")]
    protected $name;

    /**
     * ReferenceMany without cascade, removed manually via preRemove listener.
     */
    #[ORM\ReferenceMany(targetDocument: Picture::class)]
    protected $screenshots;

    public function __construct()
    {
        $this->screenshots = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function addScreenshot(Picture $screenshot): self
    {
        $this->screenshots[] = $screenshot;

        return $this;
    }

    public function removeScreenshot(Picture $screenshot): self
    {
        $this->screenshots->removeElement($screenshot);

        return $this;
    }

    public function getScreenshots(): \Doctrine\Common\Collections\Collection
    {
        return $this->screenshots;
    }
}
