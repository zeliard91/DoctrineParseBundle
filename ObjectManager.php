<?php

namespace Redking\ParseBundle;

use Doctrine\Common\Persistence\ObjectManager as BaseObjectManager;
use Doctrine\Common\EventManager;
use Parse\ParseClient;
use Parse\ParseUser;
use Parse\ParseStorageInterface;
use Redking\ParseBundle\Mapping\ClassMetadataFactory;
use Redking\ParseBundle\Proxy\ProxyFactory;

class ObjectManager implements BaseObjectManager
{
    /**
     * The metadata factory, used to retrieve the ORM metadata of entity classes.
     *
     * @var \Doctrine\ORM\Mapping\ClassMetadataFactory
     */
    private $metadataFactory;

    /**
     * The used Configuration.
     *
     * @var Redking\ParseBundle\Configuration
     */
    private $config;

    /**
     * The event manager that is the central point of the event system.
     *
     * @var \Doctrine\Common\EventManager
     */
    private $eventManager;

    /**
     * The proxy factory used to create dynamic proxies.
     *
     * @var \Redking\ParseBundle\Proxy\ProxyFactory
     */
    private $proxyFactory;

    public function __construct(Configuration $config, EventManager $eventManager, ParseStorageInterface $parseStorage)
    {
        $this->metadataFactory = new ClassMetadataFactory();
        $this->metadataFactory->setObjectManager($this);

        $this->config = $config;
        $this->eventManager = $eventManager;

        $this->unitOfWork = new UnitOfWork($this);
        $this->repositoryFactory = new RepositoryFactory();

        $this->proxyFactory = new ProxyFactory(
            $this,
            $config->getProxyDir(),
            $config->getProxyNamespace(),
            $config->getAutoGenerateProxyClasses()
        );

        $this->initParseConnection($parseStorage);
    }

    /**
     * Initialize Parse Connection.
     *
     * @param  \Parse\ParseStorageInterface $parseStorage
     * @return void
     */
    protected function initParseConnection(ParseStorageInterface $parseStorage)
    {
        $params = $this->config->getConnectionParameters();

        ParseClient::initialize($params['app_id'], $params['rest_key'], $params['master_key']);
        ParseClient::setServerURL($params['server_url'], $params['mount_path']);
        ParseClient::setStorage($parseStorage);
    }

    public function getConfiguration()
    {
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function getEventManager()
    {
        return $this->eventManager;
    }

    /**
     * @return UnitOfWork
     */
    public function getUnitOfWork()
    {
        return $this->unitOfWork;
    }

    /**
     * @return ProxyFactory
     */
    public function getProxyFactory()
    {
        return $this->proxyFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function find($className, $id)
    {
        return $this->getRepository($className)->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function persist($object)
    {
        if (!is_object($object)) {
            throw new \InvalidArgumentException(gettype($object));
        }
        $this->unitOfWork->persist($object);
    }

    /**
     * {@inheritdoc}
     */
    public function remove($object)
    {
        if (!is_object($object)) {
            throw new \InvalidArgumentException(gettype($object));
        }
        $this->unitOfWork->remove($object);
    }

    /**
     * {@inheritdoc}
     */
    public function merge($object)
    {
        if (!is_object($object)) {
            throw new \InvalidArgumentException(gettype($object));
        }
        $this->unitOfWork->merge($object);
    }

    /**
     * {@inheritdoc}
     */
    public function clear($objectName = null)
    {
        $this->unitOfWork->clear($objectName);
    }

    /**
     * {@inheritdoc}
     */
    public function detach($object)
    {
        if (!is_object($object)) {
            throw new \InvalidArgumentException(gettype($object));
        }
        $this->unitOfWork->detach($object);
    }

    /**
     * {@inheritdoc}
     */
    public function refresh($object)
    {
        if (!is_object($object)) {
            throw new \InvalidArgumentException(gettype($object));
        }
        $this->unitOfWork->refresh($object);
    }

    /**
     * {@inheritdoc}
     */
    public function flush($object = null)
    {
        if (null !== $object && !is_object($object) && !is_array($object)) {
            throw new \InvalidArgumentException(gettype($object));
        }
        $this->unitOfWork->commit($object);
    }

    /**
     * {@inheritdoc}
     */
    public function getRepository($className)
    {
        return $this->repositoryFactory->getRepository($this, $className);
    }

    /**
     * {@inheritdoc}
     */
    public function getClassMetadata($className)
    {
        return $this->metadataFactory->getMetadataFor($className);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataFactory()
    {
        return $this->metadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function initializeObject($obj)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function contains($object)
    {
        return $this->unitOfWork->isScheduledForInsert($object)
            || $this->unitOfWork->isInIdentityMap($object)
            && !$this->unitOfWork->isScheduledForDelete($object);
    }

    /**
     * Returns reference.
     *
     * @param string $objectName
     * @param string $id
     *
     * @return object
     */
    public function getReference($objectName, $id, \Parse\ParseObject $data)
    {
        $class = $this->metadataFactory->getMetadataFor(ltrim($objectName, '\\'));

        // Check identity map first, if its already in there just return it.
        if (($object = $this->unitOfWork->tryGetById($id, $class->rootEntityName)) !== false) {
            return ($object instanceof $class->name) ? $object : null;
        }

        // if ($class->subClasses) {
        //     return $this->find($objectName, $class->identifier);
        // }

        $object = $this->proxyFactory->getProxy($class->name, [$class->identifier => $id]);

        $this->unitOfWork->registerManaged($object, $data->getObjectId(), $data);

        return $object;
    }

    /**
     * reate a new Query instance for a class.
     *
     * @param string $objectName The object class name.
     *
     * @return QueryBuilder
     */
    public function createQueryBuilder($objectName)
    {
        return new QueryBuilder($this, $objectName);
    }

    /**
     * Tells if a request should be called with master key.
     *
     * @return boolean
     */
    public function isMasterRequest()
    {
        return $this->config->getAlwaysMaster() || !is_null(ParseUser::getCurrentUser());
    }
}
