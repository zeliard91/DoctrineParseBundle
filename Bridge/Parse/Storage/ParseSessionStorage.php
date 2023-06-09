<?php

namespace Redking\ParseBundle\Bridge\Parse\Storage;

use Parse\ParseStorageInterface;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Http\FirewallMapInterface;

class ParseSessionStorage implements ParseStorageInterface
{
    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var SessionFactoryInterface
     */
    private $sessionStorageFactory;

    /**
     * @var FirewallMapInterface
     */
    private $firewallMap;

    /**
     * Parse will store its values in a specific key.
     *
     * @var string
     */
    private $storageKey;

    public function __construct(RequestStack $requestStack, SessionFactoryInterface $sessionStorageFactory, FirewallMapInterface $firewallMap)
    {
        $this->requestStack = $requestStack;
        $this->sessionStorageFactory = $sessionStorageFactory;
        $this->firewallMap = $firewallMap;
    }

    /**
     * @param SessionInterface $session
     * 
     * @return void
     */
    public function setSession(SessionInterface $session): void
    {
        $this->session = $session;
    }

    /**
     * @param RequestStack $requestStack
     * 
     * @return void
     */
    public function setRequestStack(RequestStack $requestStack): void
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @return SessionInterface
     */
    public function getSession(): SessionInterface
    {
        try {
            return null !== $this->session ? $this->session : $this->requestStack->getSession();
        } catch (SessionNotFoundException $e) {
            $this->session = $this->sessionStorageFactory->createSession();

            return $this->session;
        }
    }

    protected function getStorageKey(): string
    {
        if (null === $this->storageKey) {
            $this->storageKey = '_parse_data';
            $request = $this->requestStack->getCurrentRequest();
            if (null !== $request) {
                $firewallConfig = $this->firewallMap->getFirewallConfig($request);
    
                if (null !== $firewallConfig) {
                    $this->storageKey .= '_' . $firewallConfig->getName();
                }
            }
        }

        return $this->storageKey;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value)
    {
        $data = $this->getSession()->get($this->getStorageKey());
        $data[$key] = $value;
        $this->getSession()->set($this->getStorageKey(), $data);

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key)
    {
        $data = $this->getSession()->get($this->getStorageKey());
        unset($data[$key]);
        $this->getSession()->set($this->getStorageKey(), $data);

        return null;
    }

    /**
     * @return mixed
     */
    public function get($key)
    {
        $data = $this->getSession()->get($this->getStorageKey());

        return isset($data[$key]) ? $data[$key] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->getSession()->set($this->getStorageKey(), []);

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        return null;
    }

    /**
     * @return array
     */
    public function getKeys()
    {
        return array_keys($this->getSession()->get($this->getStorageKey()));
    }

    /**
     * @return array
     */
    public function getAll()
    {
        return $this->getSession()->get($this->getStorageKey());
    }
}