<?php

namespace Redking\ParseBundle\Bridge\HWIOauth;

use FOS\UserBundle\Model\User;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwner\FacebookResourceOwner;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwner\TwitterResourceOwner;
use HWI\Bundle\OAuthBundle\Security\Core\Exception\AccountNotLinkedException;
use HWI\Bundle\OAuthBundle\Security\Core\User\FOSUBUserProvider;
use Parse\ParseClient;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;

class UserProvider extends FOSUBUserProvider
{
    /**
     * {@inheritdoc}
     */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        try {
            return parent::loadUserByOAuthUserResponse($response);
        } catch (AccountNotLinkedException $e) {
            // try to load by email
            if ($response->getEmail() !== null) {
                $user = $this->userManager->findUserByEmail($response->getEmail());
                if (is_null($user)) {
                    throw $e;
                }
                
                // If a user is found with this email, link it with auth data and returns
                $property = $this->getProperty($response);
                $this->accessor->setValue($user, $property, $response->getUsername());
                $user->setAuthData($this->getParseAuthData($response));
                $this->userManager->updateUser($user);

                return $user;
            }
        }
    }


    /**
     * {@inheritDoc}
     */
    public function connect(UserInterface $user, UserResponseInterface $response)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Expected an instance of FOS\UserBundle\Model\User, but got "%s".', get_class($user)));
        }

        $property = $this->getProperty($response);

        // Symfony <2.5 BC
        if (method_exists($this->accessor, 'isWritable') && !$this->accessor->isWritable($user, $property)
            || !method_exists($this->accessor, 'isWritable') && !method_exists($user, 'set'.ucfirst($property))) {
            throw new \RuntimeException(sprintf("Class '%s' must have defined setter method for property: '%s'.", get_class($user), $property));
        }

        $username = $response->getUsername();

        if (null !== $previousUser = $this->userManager->findUserBy(array($property => $username))) {
            $this->disconnect($previousUser, $response);
        }

        $this->accessor->setValue($user, $property, $username);

        if ($response->getResourceOwner() instanceof TwitterResourceOwner) {
            $user->setAuthData($this->getParseAuthData($response));
            // save profile picture
            if (method_exists($user, 'setAvatarFromUrl')) {
                $user->setAvatarFromUrl($response->getResponse()['profile_image_url']);
            }


        } elseif ($response->getResourceOwner() instanceof FacebookResourceOwner) {
            $user->setAuthData($this->getParseAuthData($response));
            $user->setFirstname($response->getFirstName());
            $user->setLastname($response->getLastName());

            // save profile picture
            if (method_exists($user, 'setAvatarFromUrl')) {
                $user->setAvatarFromUrl('https://graph.facebook.com/'.$response->getResponse()['id'].'/picture?type=large');
            }
        }

        $this->userManager->updateUser($user);
    }

    /**
     * Builds and returns Parse authData from OAuth response.
     *
     * @param  UserResponseInterface $response
     * @return array
     */
    protected function getParseAuthData(UserResponseInterface $response)
    {
        if ($response->getResourceOwner() instanceof TwitterResourceOwner) {
            $authData = ['twitter' => ''];
            $authData['twitter']['id'] = $response->getUsername();
            $authData['twitter']['screen_name'] = $response->getResponse()['screen_name'];
            $authData['twitter']['consumer_key'] = $response->getResourceOwner()->getOption('client_id');
            $authData['twitter']['consumer_secret'] = $response->getResourceOwner()->getOption('client_secret');
            $authData['twitter']['auth_token'] = $response->getAccessToken();
            $authData['twitter']['auth_token_secret'] = $response->getTokenSecret();
            
            return $authData;
        }

        if ($response->getResourceOwner() instanceof FacebookResourceOwner) {
            $authData = ['facebook' => ''];
            $authData['facebook']['id'] = $response->getResponse()['id'];
            $authData['facebook']['access_token'] = $response->getAccessToken();
            $expiration_date = new \DateTime();
            $expiration_date->add(new \DateInterval('PT'.$response->getExpiresIn().'S'));
            $authData['facebook']['expiration_date'] = ParseClient::getProperDateFormat($expiration_date);
            
            return $authData;
        }
    }
}
