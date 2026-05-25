<?php

namespace Redking\ParseBundle\Tests\Models\Blog;

use Redking\ParseBundle\Mapping\Annotations as ORM;

/**
 * Self-referencing fixture used to exercise the multi-includeKey hydration
 * path where a Pointer field nested inside one top-level include refers to
 * the entity provided by a sibling top-level include.
 */
#[ORM\ParseObject(collection: "blog_chain_node")]
class ChainNode
{
    use \Redking\ParseBundle\ObjectTrait;

    #[ORM\Field(type: "string")]
    private ?string $label = null;

    #[ORM\ReferenceOne(targetDocument: self::class)]
    private ?ChainNode $first = null;

    #[ORM\ReferenceOne(targetDocument: self::class)]
    private ?ChainNode $second = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getFirst(): ?ChainNode
    {
        return $this->first;
    }

    public function setFirst(?ChainNode $first): self
    {
        $this->first = $first;

        return $this;
    }

    public function getSecond(): ?ChainNode
    {
        return $this->second;
    }

    public function setSecond(?ChainNode $second): self
    {
        $this->second = $second;

        return $this;
    }
}
