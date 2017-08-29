<?php

namespace Redking\ParseBundle\Bridge\Parse\Storage;

use Parse\ParseStorageInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ParseSessionStorage implements ParseStorageInterface
{
    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * Parse will store its values in a specific key.
     *
     * @var string
     */
    private $storageKey = '_parse_data';

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value)
    {
        $data = $this->session->get($this->storageKey);
        $data[$key] = $value;
        $this->session->set($this->storageKey, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key)
    {
        $data = $this->session->get($this->storageKey);
        unset($data[$key]);
        $this->session->set($this->storageKey, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        $data = $this->session->get($this->storageKey);

        return isset($data[$key]) ? $data[$key] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->session->set($this->storageKey, []);
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        return;
    }

    /**
     * {@inheritdoc}
     */
    public function getKeys()
    {
        return array_keys($this->session->get($this->storageKey));
    }

    /**
     * {@inheritdoc}
     */
    public function getAll()
    {
        return $this->session->get($this->storageKey);
    }
}