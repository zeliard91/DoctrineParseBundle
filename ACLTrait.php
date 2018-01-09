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
     * Get ACL Key for data (could be a string, _User, _Role ...)
     *
     * @param  mixed  $data
     * @param  boolean $useName
     * @return string
     */
    private function getAclKeyForData($data, $useName = false)
    {
        if (is_scalar($data)) {
            return $data;
        }
        if (is_object($data)) {
            if ($useName && method_exists($data, 'getName') && null !== $data->getName()) {
                return $data->getName();
            }
            if (method_exists($data, 'getId') && null !== $data->getId()) {
                return $data->getId();
            }

            return spl_object_hash($data);
        }
    }

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
     * @return boolean
     */
    public function getPublicAclReadAccess()
    {
        if (null !== $this->_publicAcl) {
            return $this->_publicAcl->getPublicReadAccess();
        }

        return true;
    }

    /**
     * @return boolean
     */
    public function getPublicAclWriteAccess()
    {
        if (null !== $this->_publicAcl) {
            return $this->_publicAcl->getPublicWriteAccess();
        }

        return true;
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
        $key = $this->getAclKeyForData($role, true);

        $this->_rolesAcl[$key] = [
            'role' => $role,
            'read' => $read,
            'write' => $write,
        ];
    }

    /**
     * @param  string|object  $role
     * @return boolean
     */
    public function hasRoleAcl($role)
    {
        $key = $this->getAclKeyForData($role, true);

        return isset($this->_rolesAcl[$key]);
    }

    /**
     * Remove Role from the ACLs.
     *
     * @param  string|object $role
     * @return
     */
    public function removeRoleAcl($role)
    {
        if ($this->hasRoleAcl($role)) {
            unset($this->_rolesAcl[$this->getAclKeyForData($role, true)]);
        }
    }

    /**
     * @return array
     */
    public function getRolesAcl()
    {
        return $this->_rolesAcl;
    }

    /**
     * @param array $rolesAcl
     */
    public function setRolesAcl(array $rolesAcl)
    {
        $this->_rolesAcl = $rolesAcl;

        return $this;
    }

    /**
     * @param  object|string $role
     * @return boolean
     */
    public function getRoleAclReadAccess($role)
    {
        $key = $this->getAclKeyForData($role, true);

        if (isset($this->_rolesAcl[$key])) {
            return $this->_rolesAcl[$key]['read'];
        }

        return $this->getPublicAclReadAccess();
    }

    /**
     * @param  object|string $role
     * @return boolean
     */
    public function getRoleAclWriteAccess($role)
    {
        $key = $this->getAclKeyForData($role, true);

        if (isset($this->_rolesAcl[$key])) {
            return $this->_rolesAcl[$key]['write'];
        }

        return $this->getPublicAclWriteAccess();
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
        $key = $this->getAclKeyForData($user);

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

    /**
     * @param array $usersAcl
     */
    public function setUsersAcl(array $usersAcl)
    {
        $this->_usersAcl = $usersAcl;

        return $this;
    }

    /**
     * Remove User from the ACLs.
     *
     * @param  string|object $user
     * @return
     */
    public function removeUserAcl($user)
    {
        $key = $this->getAclKeyForData($user);

        if (isset($this->_usersAcl[$key])) {
            unset($this->_usersAcl[$this->getAclKeyForData($user)]);
        }
    }

    /**
     * @param  object|string $user
     * @return boolean
     */
    public function getUserAclReadAccess($user)
    {
        $key = $this->getAclKeyForData($user);

        if (isset($this->_usersAcl[$key])) {
            return $this->_usersAcl[$key]['read'];
        }

        return $this->getPublicAclReadAccess();
    }

    /**
     * @param  object|string $user
     * @return boolean
     */
    public function getUserAclWriteAccess($user)
    {
        $key = $this->getAclKeyForData($user);

        if (isset($this->_usersAcl[$key])) {
            return $this->_usersAcl[$key]['write'];
        }

        return $this->getPublicAclWriteAccess();
    }
}
