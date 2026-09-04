<?php

namespace TestObjects;

use Redking\ParseBundle\Mapping\Annotations as ORM;

class IndexedUser
{
    protected $id;

    protected $username;

    protected $email;

    protected $createdAt;

    protected $updatedAt;

    protected $address;

    public function getId()
    {
        return $this->id;
    }
}
