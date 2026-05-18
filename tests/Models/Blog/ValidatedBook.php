<?php

namespace Redking\ParseBundle\Tests\Models\Blog;

use Redking\ParseBundle\Mapping\Annotations as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Owning side of a OneToMany relation (ReferenceOne back to the author).
 * Carries a NotBlank constraint on its title to assert that validation
 * cascaded by Assert\Valid actually reaches the lazily-loaded children.
 */
#[ORM\ParseObject(collection: "blog_validated_book")]
class ValidatedBook
{
    use \Redking\ParseBundle\ObjectTrait;

    #[ORM\Field(type: "string")]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\ReferenceOne(targetDocument: ValidatedAuthor::class, inversedBy: "books")]
    private ?ValidatedAuthor $author = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getAuthor(): ?ValidatedAuthor
    {
        return $this->author;
    }

    public function setAuthor(?ValidatedAuthor $author): self
    {
        $this->author = $author;

        return $this;
    }
}
