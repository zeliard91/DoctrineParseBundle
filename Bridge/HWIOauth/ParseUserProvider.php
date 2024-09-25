<?php

namespace Redking\ParseBundle\Bridge\HWIOauth;

use DateInterval;
use DateTime;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\Exception\AccountNotLinkedException;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use Parse\ParseClient;
use Redking\ParseBundle\ObjectManager;
use Redking\ParseBundle\ObjectRepository;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\String\ByteString;

class ParseUserProvider implements UserProviderInterface, OAuthAwareUserProviderInterface
{
    private ObjectManager $om;
    private string $class;
    private ?ObjectRepository $repository = null;
    private PropertyAccessorInterface $propertyAccessor;

    /**
     * @var array<string, string>
     */
    private array $properties = [
        'identifier' => 'id',
    ];

    /**
     * @param string                $class      User entity class to load
     * @param array<string, string> $properties Mapping of resource owners to properties
     */
    public function __construct(ObjectManager $om, string $class, array $properties)
    {
        $this->om = $om;
        $this->class = $class;
        $this->properties = array_merge($this->properties, $properties);
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->findUser(['username' => $identifier]);

        if (!$user) {
            throw $this->createUserNotFoundException($identifier, sprintf("User '%s' not found.", $identifier));
        }

        return $user;
    }

    public function loadUserByOAuthUserResponse(UserResponseInterface $response): ?UserInterface
    {
        $resourceOwnerName = $response->getResourceOwner()->getName();

        if (!isset($this->properties[$resourceOwnerName])) {
            throw new \RuntimeException(sprintf("No property defined for entity for resource owner '%s'.", $resourceOwnerName));
        }

        $username = method_exists($response, 'getUserIdentifier') ? $response->getUserIdentifier() : $response->getUsername();
        $classmetadata = $this->om->getClassMetadata($this->class);

        // Try to find user by Parse Auth Data
        $user = $this->findUserByAuthUsername($resourceOwnerName, $username);

        // Or try to find it by resource name id
        if (null === $user && $classmetadata->hasField($this->properties[$resourceOwnerName])) {
            $user = $this->findUser([$this->properties[$resourceOwnerName] => $username]);
        }

        // Finally, try to find by the username given by auth response
        if (null === $user) {
            $user = $this->findUser(['email' => $response->getEmail()]);
        }

        if (null === $user) {
            // Auto create user if not found
            $user = new $this->class;
            $userData = [
                $this->properties[$resourceOwnerName] => $response->getUserIdentifier(),
                'email' => $response->getEmail(),
                'username' => $response->getEmail(),
                'password' => ByteString::fromRandom(18),
                'firstName' => $response->getFirstName(),
                'lastName' => $response->getLastName(),
                'lastLoginAt' => new DateTime(),
                'authData' => $this->getParseAuthData($response),
            ];

            foreach ($userData as $fieldName => $value) {
                if ($this->propertyAccessor->isWritable($user, $fieldName)) {
                    $this->propertyAccessor->setValue($user, $fieldName, $value);
                }
            }

            $this->om->persist($user);
            $this->om->flush();
        } else {
            $userData = [
                $this->properties[$resourceOwnerName] => $response->getUserIdentifier(),
                'lastLoginAt' => new DateTime(),
                'authData' => $this->getParseAuthData($response, $user),
            ];

            foreach ($userData as $fieldName => $value) {
                if ($this->propertyAccessor->isWritable($user, $fieldName)) {
                    $this->propertyAccessor->setValue($user, $fieldName, $value);
                }
            }

            $this->om->flush();
        }

        return $user;
    }

    private function findUser(array $criteria): ?UserInterface
    {
        $this->om->getConfiguration()->setAlwaysMaster(true);
        if (null === $this->repository) {
            $this->repository = $this->om->getRepository($this->class);
        }

        return $this->repository->findOneBy($criteria);
    }

    private function findUserByAuthUsername(string $authService, string $username): ?UserInterface
    {
        $this->om->getConfiguration()->setAlwaysMaster(true);
        if (null === $this->repository) {
            $this->repository = $this->om->getRepository($this->class);
        }

        $pipeline = [
            'match' => [
                '_auth_data_' . $authService . '.id' => $username
            ],
            'project' => [
                'objectId' => 1,
            ]
        ];
        $results = $this->repository->createQueryBuilder()
            ->aggregate($pipeline)
            ->getQuery()
            ->execute()
        ;

        if (count($results) > 0) {
            $userId = $results[0]['objectId'];

            return $this->repository->find($userId);
        }

        return null;
    }

    /**
     * Symfony <5.4 BC layer.
     *
     * @param string $username
     *
     * @return UserInterface
     */
    public function loadUserByUsername($username)
    {
        return $this->loadUserByIdentifier($username);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $user;
    }

    public function supportsClass($class): bool
    {
        return $class === $this->class || is_subclass_of($class, $this->class);
    }

    private function createUserNotFoundException(string $username, string $message): UserNotFoundException
    {
        $exception = new AccountNotLinkedException($message);
        $exception->setUserIdentifier($username);

        return $exception;
    }

    protected function getParseAuthData(UserResponseInterface $response, ?UserInterface $user = null): array
    {
        if (null === $user) {
            $authData = [];
        } elseif ($this->propertyAccessor->isReadable($user, 'authData')) {
            $authData = $this->propertyAccessor->getValue($user, 'authData') ?? [];
        }

        switch ($response->getResourceOwner()->getName()) {
            case 'google':
                $authData['google'] = [
                    'id' => $response->getUserIdentifier(),
                    'access_token' => $response->getAccessToken(),
                    'id_token' => $response->getOAuthToken()->getRawToken()['id_token'],
                ];
                break;

            case 'apple':
                $authData['apple'] = [
                    'id' => $response->getUserIdentifier(),
                    'token' => $response->getAccessToken(),
                ];
                break;

            case 'facebook':
                $expirationDate = new DateTime();
                $expirationDate->add(new DateInterval('PT' . $response->getExpiresIn() . 'S'));
                $authData['facebook'] = [
                    'id' => $response->getUserIdentifier(),
                    'access_token' => $response->getAccessToken(),
                    'expiration_date' => ParseClient::getProperDateFormat($expirationDate),
                ];
                break;
        }

        return $authData;
    }
}