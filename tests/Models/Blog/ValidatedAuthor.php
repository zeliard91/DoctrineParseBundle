<?php

namespace Redking\ParseBundle\Tests\Models\Blog;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Redking\ParseBundle\Mapping\Annotations as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Inverse side of a OneToMany relation (mappedBy on the child).
 * Carries an Assert\Valid constraint to cascade validation into the
 * lazy-loaded collection of children.
 */
#[ORM\ParseObject(collection: "blog_validated_author")]
class ValidatedAuthor
{
    use \Redking\ParseBundle\ObjectTrait;

    #[ORM\Field(type: "string")]
    private ?string $name = null;

    #[ORM\ReferenceMany(targetDocument: ValidatedBook::class, mappedBy: "author", cascade: "all")]
    #[Assert\Valid]
    private Collection $books;

    public function __construct()
    {
        $this->books = new ArrayCollection();
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

    public function getBooks(): Collection
    {
        return $this->books;
    }

    public function addBook(ValidatedBook $book): self
    {
        $book->setAuthor($this);
        $this->books[] = $book;

        return $this;
    }

    public function removeBook(ValidatedBook $book): self
    {
        $this->books->removeElement($book);

        return $this;
    }
}
