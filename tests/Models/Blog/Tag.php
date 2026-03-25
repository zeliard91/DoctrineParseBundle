<?php

namespace Redking\ParseBundle\Tests\Models\Blog;

use Redking\ParseBundle\Mapping\Annotations as ORM;

/**
 * Inverse side of a ReferenceMany owning relation.
 * Article stores an array of Tag pointers (owning side),
 * Tag exposes a lazy ReferenceOne back to Article (inverse via mappedBy).
 */
#[ORM\ParseObject(collection: "blog_tag")]
class Tag
{
    use \Redking\ParseBundle\ObjectTrait;

    #[ORM\Field(type: "string")]
    private $name;

    #[ORM\ReferenceOne(targetDocument: Article::class, mappedBy: "tags", fetch: "LAZY")]
    private $article;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): self
    {
        $this->article = $article;

        return $this;
    }
}
