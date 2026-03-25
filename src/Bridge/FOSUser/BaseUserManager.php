<?php

namespace Redking\ParseBundle\Bridge\FOSUser;

use FOS\UserBundle\Doctrine\UserManager as FOSUserManager;
use FOS\UserBundle\Model\UserInterface;
use Parse\ParseClient;
use Parse\ParseUser;

class BaseUserManager extends FOSUserManager
{
    /**
     * Finds a user by username
     *
     * @param string $username
     *
     * @return UserInterface
     */
    public function findUserByUsername($username)
    {
        return $this->findUserBy(array('username' => $username));
    }

    /**
     * Finds a user by email
     *
     * @param string $email
     *
     * @return UserInterface
     */
    public function findUserByEmail($email)
    {
        return $this->findUserBy(array('email' => $email));
    }
}
