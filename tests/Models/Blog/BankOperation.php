<?php

namespace Redking\ParseBundle\Tests\Models\Blog;

use Redking\ParseBundle\Mapping\Annotations as ORM;

#[ORM\ParseObject(collection: "blog_bank_operation")]
class BankOperation
{
    use \Redking\ParseBundle\ObjectTrait;

    #[ORM\Field(type: "float")]
    private $amount;

    #[ORM\ReferenceOne(targetDocument: Invoice::class, inversedBy: "bankOperation")]
    private $invoice;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): self
    {
        $this->invoice = $invoice;

        return $this;
    }
}
