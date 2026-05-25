<?php

namespace Redking\ParseBundle\Tests\Models\Blog;

use Parse\ParseACL;
use Redking\ParseBundle\Mapping\Annotations as ORM;

/**
 * Self-referencing fixture mimicking a user-land entity whose ACL accessors
 * may return null instead of [] (e.g. when the class overrides the property
 * without a default, or implements its own ACL stack without inline defaults).
 *
 * Used to assert that getAcl() does not crash on a freshly pre-registered
 * managed instance whose ACL collections are still null.
 */
#[ORM\ParseObject(collection: "blog_null_acl_chain_node")]
class NullAclChainNode
{
    #[ORM\Id]
    protected $id;

    #[ORM\Field(type: "date")]
    protected $createdAt;

    #[ORM\Field(type: "date")]
    protected $updatedAt;

    #[ORM\Field(type: "string")]
    private ?string $label = null;

    #[ORM\ReferenceOne(targetDocument: self::class)]
    private ?NullAclChainNode $first = null;

    #[ORM\ReferenceOne(targetDocument: self::class)]
    private ?NullAclChainNode $second = null;

    private ?ParseACL $_publicAcl = null;

    /**
     * Intentionally NOT initialized to [] — emulates an entity whose ACL
     * accessors can legitimately return null on a fresh, unconstructed
     * instance produced by Doctrine\Instantiator.
     */
    private ?array $_rolesAcl = null;
    private ?array $_usersAcl = null;

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

    public function getFirst(): ?self
    {
        return $this->first;
    }

    public function setFirst(?self $first): self
    {
        $this->first = $first;

        return $this;
    }

    public function getSecond(): ?self
    {
        return $this->second;
    }

    public function setSecond(?self $second): self
    {
        $this->second = $second;

        return $this;
    }

    public function getPublicAcl(): ?ParseACL
    {
        return $this->_publicAcl;
    }

    public function setPublicAcl($read, $write): void
    {
        $acl = new ParseACL();
        $acl->setPublicReadAccess($read);
        $acl->setPublicWriteAccess($write);
        $this->_publicAcl = $acl;
    }

    public function publicAcl(ParseACL $acl): self
    {
        $this->_publicAcl = $acl;

        return $this;
    }

    public function getRolesAcl(): ?array
    {
        return $this->_rolesAcl;
    }

    public function getUsersAcl(): ?array
    {
        return $this->_usersAcl;
    }

    public function addRoleAcl($role, $read, $write): void
    {
        $this->_rolesAcl[] = ['role' => $role, 'read' => $read, 'write' => $write];
    }

    public function addUserAcl($user, $read, $write): void
    {
        $this->_usersAcl[] = ['user' => $user, 'read' => $read, 'write' => $write];
    }
}
