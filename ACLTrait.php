<?php

namespace Redking\ParseBundle;

use Parse\ParseACL;

/**
 * ACL Traits for Doctrine Parse Objects
 */
trait ACLTrait
{
    /**
     * @var \Parse\ParseACL
     */
    private $_publicAcl;

    /**
     * @var array
     */
    private $_rolesAcl = [];

    /**
     * @var array
     */
    private $_usersAcl = [];

    /**
     * Set public ACL.
     *
     * @param boolean $read
     * @param boolean $write
     */
    public function setPublicAcl($read, $write)
    {
        $this->_publicAcl = new ParseACL();
        $this->_publicAcl->setPublicReadAccess($read);
        $this->_publicAcl->setPublicWriteAccess($write);
    }

    /**
     * @return null|ParseACL
     */
    public function getPublicAcl()
    {
        return $this->_publicAcl;
    }

    /**
     * Add ACL for a Role
     *
     * @param string|object $role
     * @param boolean       $read
     * @param boolean       $write
     */
    public function addRoleAcl($role, $read, $write)
    {
        $key = $role;
        if (!is_scalar($role)) {
            $key = spl_object_hash($role);
        }

        $this->_rolesAcl[$key] = [
            'role' => $role,
            'read' => $read,
            'write' => $write,
        ];
    }

    /**
     * [hasRoleAcl description]
     * @param  [type]  $role [description]
     * @return boolean       [description]
     */
    public function hasRoleAcl($role)
    {
        $key = $role;
        if (!is_scalar($role)) {
            $key = spl_object_hash($role);
        }

        return isset($this->_rolesAcl[$key]);
    }

    /**
     * @return array
     */
    public function getRolesAcl()
    {
        return $this->_rolesAcl;
    }

    /**
     * Add ACL for a User
     *
     * @param string|object $user
     * @param boolean       $read
     * @param boolean       $write
     */
    public function addUserAcl($user, $read, $write)
    {
        $key = $user;
        if (!is_scalar($user)) {
            $key = spl_object_hash($user);
        }

        $this->_usersAcl[$key] = [
            'user' => $user,
            'read' => $read,
            'write' => $write,
        ];
    }

    /**
     * @return array
     */
    public function getUsersAcl()
    {
        return $this->_usersAcl;
    }
}
