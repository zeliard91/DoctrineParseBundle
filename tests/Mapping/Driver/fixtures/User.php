<?php

namespace TestObjects;

use Redking\ParseBundle\Mapping\Annotations as ORM;

/**
 * @ORM\ParseObject(collection="user")
 */
class User
{
    /**
     * @ORM\Id
     */
    protected $id;

    /**
     * @ORM\Field(type="string")
     */
    protected $username;

    protected $password;

    /**
     * @ORM\Field(type="DateTime")
     */
    protected $createdAt;

    /**
     * @ORM\Field(type="DateTime")
     */
    protected $updatedAt;

    /**
     * @ORM\ReferenceOne(targetDocument="TestObjects\Address")
     */
    protected $address;

    protected $profile;

    /**
     * @ORM\ReferenceMany(targetDocument="TestObjects\PhoneNumber", cascade="all")
     */
    protected $phoneNumbers;

    protected $groups;

    protected $account;

    /**
     * @ORM\Field(type="array")
     */
    protected $tags = array();

    protected $test;

    public function __construct()
    {
        $this->phoneNumbers = new \Doctrine\Common\Collections\ArrayCollection();
        $this->groups = array();
        $this->createdAt = new \DateTime();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getAddress()
    {
        return $this->address;
    }

    public function setAddress(Address $address)
    {
        $this->address = $address;
    }

    public function setProfile(Profile $profile)
    {
        $this->profile = $profile;
    }

    public function getProfile()
    {
        return $this->profile;
    }

    public function setAccount(Account $account)
    {
        $this->account = $account;
    }

    public function getAccount()
    {
        return $this->account;
    }

    public function getPhoneNumbers()
    {
        return $this->phoneNumbers;
    }

    public function addPhoneNumber(Phonenumber $phonenumber)
    {
        $this->phoneNumbers[] = $phonenumber;
    }

    public function getGroups()
    {
        return $this->groups;
    }

    public function addGroup(Group $group)
    {
        $this->groups[] = $group;
    }
}