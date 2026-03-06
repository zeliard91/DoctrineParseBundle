<?php

namespace Redking\ParseBundle\Tests\Models\Blog;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Redking\ParseBundle\Mapping\Annotations as ORM;

/**
 * Owning side of a ReferenceMany relation toward Tag.
 * Article stores the array of Tag pointers.
 */
#[ORM\ParseObject(collection: "blog_article")]
class Article
{
    use \Redking\ParseBundle\ObjectTrait;

    #[ORM\Field(type: "string")]
    private $title;

    #[ORM\ReferenceMany(targetDocument: Tag::class, inversedBy: "article")]
    private $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): self
    {
        $this->tags[] = $tag;

        return $this;
    }

    public function removeTag(Tag $tag): self
    {
        $this->tags->removeElement($tag);

        return $this;
    }
}
