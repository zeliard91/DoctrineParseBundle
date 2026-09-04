<?php

namespace Redking\ParseBundle\Tests\Models\Blog;

use Redking\ParseBundle\Mapping\Annotations as ORM;

#[ORM\ParseObject(collection: "blog_indexed_article")]
#[ORM\Index(keys: ["title" => "asc", "author" => "asc"], name: "title_author")]
class IndexedArticle
{
    use \Redking\ParseBundle\ObjectTrait;

    #[ORM\Field(type: "string")]
    #[ORM\Index]
    private $title;

    #[ORM\Field(type: "integer", name: "hits")]
    #[ORM\Index(order: "desc")]
    private $viewCount;

    #[ORM\Field(type: "boolean")]
    private $published;

    #[ORM\ReferenceOne(targetDocument: "Redking\ParseBundle\Tests\Models\Blog\User")]
    private $author;

    public function getId()
    {
        return $this->id;
    }

    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setViewCount($viewCount)
    {
        $this->viewCount = $viewCount;

        return $this;
    }

    public function getViewCount()
    {
        return $this->viewCount;
    }

    public function setPublished($published)
    {
        $this->published = $published;

        return $this;
    }

    public function isPublished()
    {
        return $this->published;
    }

    public function setAuthor(?User $author)
    {
        $this->author = $author;

        return $this;
    }

    public function getAuthor()
    {
        return $this->author;
    }
}
