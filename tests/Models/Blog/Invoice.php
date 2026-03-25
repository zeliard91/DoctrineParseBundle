<?php

namespace Redking\ParseBundle\Tests\Models\Blog;

use Redking\ParseBundle\Mapping\Annotations as ORM;

#[ORM\ParseObject(collection: "blog_invoice")]
class Invoice
{
    use \Redking\ParseBundle\ObjectTrait;

    #[ORM\Field(type: "string")]
    private $reference;

    #[ORM\ReferenceOne(targetDocument: BankOperation::class, mappedBy: "invoice", fetch: "LAZY")]
    private $bankOperation;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getBankOperation(): ?BankOperation
    {
        return $this->bankOperation;
    }

    public function setBankOperation(?BankOperation $bankOperation): self
    {
        $this->bankOperation = $bankOperation;

        return $this;
    }
}
