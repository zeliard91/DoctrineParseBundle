<?php

namespace Redking\ParseBundle\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\SimpleFormAuthenticatorInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\DisabledException;

use Parse\ParseClient;
use Parse\ParseException;

/**
 * Brings authentication to Parse Users
 */
class ParseAuthenticator implements SimpleFormAuthenticatorInterface
{
    
    public function authenticateToken(TokenInterface $token, UserProviderInterface $userProvider, $providerKey)
    {
        // user provider manage throwing exception if not found
        $user = $userProvider->loadUserByUsername($token->getUsername());

        $data = ['username' => $user->getUsername(), 'password' => $token->getCredentials()];

        try {
            $result = ParseClient::_request('GET', 'login', '', $data, true);
        } catch (ParseException $e) {
            throw new BadCredentialsException('The presented password is invalid.');
        }

        if (!$user->isEnabled()) {
            $ex = new DisabledException('User account is disabled.');
            $ex->setUser($user);
            throw $ex;
        }
        
        return new UsernamePasswordToken(
                $user,
                $token->getCredentials(),
                $providerKey,
                $user->getRoles()
            );
    }

    public function supportsToken(TokenInterface $token, $providerKey)
    {
        return $token instanceof UsernamePasswordToken
            && $token->getProviderKey() === $providerKey;
    }

    public function createToken(Request $request, $username, $password, $providerKey)
    {
        return new UsernamePasswordToken($username, $password, $providerKey);
    }
}
