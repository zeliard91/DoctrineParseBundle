<?php

namespace Redking\ParseBundle\Tests\Models\Blog;

use Redking\ParseBundle\Mapping\Annotations as ORM;

#[ORM\ParseObject(collection: "SecureDocument")]
class SecureDocument
{
    use \Redking\ParseBundle\ObjectTrait;

    #[ORM\Field(type: "string")]
    private $title;

    #[ORM\Field(type: "encrypted_string", name: "secret_note")]
    private $secretNote;

    #[ORM\Field(type: "encrypted_string")]
    private $ssn;

    #[ORM\Field(type: "string")]
    private $publicInfo;

    public function getId()
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

    public function getSecretNote(): ?string
    {
        return $this->secretNote;
    }

    public function setSecretNote(?string $secretNote): self
    {
        $this->secretNote = $secretNote;
        return $this;
    }

    public function getSsn(): ?string
    {
        return $this->ssn;
    }

    public function setSsn(?string $ssn): self
    {
        $this->ssn = $ssn;
        return $this;
    }

    public function getPublicInfo(): ?string
    {
        return $this->publicInfo;
    }

    public function setPublicInfo(?string $publicInfo): self
    {
        $this->publicInfo = $publicInfo;
        return $this;
    }
}
