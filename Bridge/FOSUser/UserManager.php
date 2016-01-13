<?php

namespace Redking\ParseBundle\Bridge\FOSUser;

use FOS\UserBundle\Doctrine\UserManager as BaseUserManager;
use FOS\UserBundle\Model\UserInterface;
use Parse\ParseClient;
use Parse\ParseUser;

class UserManager extends BaseUserManager
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
        return $this->findUserBy(array('username' => $this->canonicalizeUsername($username)));
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
        return $this->findUserBy(array('email' => $this->canonicalizeEmail($email)));
    }

    /**
     * Prevent encrypting of password as Parse use encryption.
     */
    public function updatePassword(UserInterface $user)
    {
        if (0 !== strlen($password = $user->getPlainPassword())) {
            $user->setPassword($password);
            $user->eraseCredentials();
        }
    }
}
