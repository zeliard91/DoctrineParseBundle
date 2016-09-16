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

    /**
     * Clear the password after the update so that the object be the same as when it is fetched
     * (fix bug in reset password process where token->hasUserChanged() returns true after success reset -> redirect loop)
     *
     * @param UserInterface $user
     * @param Boolean       $andFlush Whether to flush the changes (default true)
     */
    public function updateUser(UserInterface $user, $andFlush = true)
    {
        parent::updateUser($user, $andFlush);

        $user->setPassword(null);
    }
}
