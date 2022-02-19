<?php

namespace Redking\ParseBundle\Bridge\Parse\Storage;

use Parse\ParseStorageInterface;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

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
     * Parse will store its values in a specific key.
     *
     * @var string
     */
    private $storageKey = '_parse_data';

    /**
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
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
            return new Session();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value)
    {
        $data = $this->getSession()->get($this->storageKey);
        $data[$key] = $value;
        $this->getSession()->set($this->storageKey, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key)
    {
        $data = $this->getSession()->get($this->storageKey);
        unset($data[$key]);
        $this->getSession()->set($this->storageKey, $data);
    }

    /**
     * @return mixed
     */
    public function get($key)
    {
        $data = $this->getSession()->get($this->storageKey);

        return isset($data[$key]) ? $data[$key] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->getSession()->set($this->storageKey, []);
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        return;
    }

    /**
     * @return array
     */
    public function getKeys()
    {
        return array_keys($this->getSession()->get($this->storageKey));
    }

    /**
     * @return array
     */
    public function getAll()
    {
        return $this->getSession()->get($this->storageKey);
    }
}