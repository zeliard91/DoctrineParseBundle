<?php

namespace Redking\ParseBundle;

use Doctrine\Persistence\ObjectManager as BaseObjectManager;
use Doctrine\Common\EventManager;
use Parse\ParseClient;
use Parse\ParseObject;
use Parse\ParseUser;
use Parse\ParseStorageInterface;
use Redking\ParseBundle\HttpClient\SymfonyClient;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\Mapping\ClassMetadataFactory;
use Redking\ParseBundle\Proxy\ProxyFactory;

class ObjectManager implements BaseObjectManager
{
    /**
     * The metadata factory, used to retrieve the ORM metadata of entity classes.
     *
     * @var \Redking\ParseBundle\Mapping\ClassMetadataFactory
     */
    private $metadataFactory;

    /**
     * The used Configuration.
     *
     * @var \Redking\ParseBundle\Configuration
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

    /**
     * Unit of work.
     *
     * @var \Redking\ParseBundle\UnitOfWork
     */
    private $unitOfWork;

    /**
     * @var SchemaManager
     */
    private $schemaManager;

    /**
     * Repository Factory.
     *
     * @var \Redking\ParseBundle\RepositoryFactory
     */
    private $repositoryFactory;

    public function __construct(Configuration $config = null, EventManager $eventManager = null, ParseStorageInterface $parseStorage = null, SymfonyClient $symfonyClient = null)
    {
        $this->config = $config ?: new Configuration();
        $this->metadataFactory = new ClassMetadataFactory();

        $this->metadataFactory->setObjectManager($this);
        if ($cacheDriver = $this->config->getMetadataCache()) {
            $this->metadataFactory->setCache($cacheDriver);
        }

        $this->eventManager = $eventManager ?: new EventManager();

        $this->unitOfWork = new UnitOfWork($this);
        $this->repositoryFactory = new RepositoryFactory();

        $this->proxyFactory = new ProxyFactory(
            $this,
            $config->getProxyDir(),
            $config->getProxyNamespace(),
            $config->getAutoGenerateProxyClasses()
        );

        $this->schemaManager = new SchemaManager($this, $this->metadataFactory);

        $this->initParseConnection($parseStorage);
        $this->setHttpClient($symfonyClient);
    }

    /**
     * Creates a new Document that operates on the given Mongo connection
     * and uses the given Configuration.
     */
    public static function create(Configuration $config = null, EventManager $eventManager = null, ParseStorageInterface $parseStorage = null, SymfonyClient $symfonyClient = null): self
    {
        return new static($config, $eventManager, $parseStorage, $symfonyClient);
    }

    /**
     * Initialize Parse Connection.
     *
     * @param  \Parse\ParseStorageInterface $parseStorage
     * @return void
     */
    protected function initParseConnection(ParseStorageInterface $parseStorage = null)
    {
        if (null !== $parseStorage) {
            ParseClient::setStorage($parseStorage);
        }

        $params = $this->config->getConnectionParameters();
        ParseClient::initialize($params['app_id'], $params['rest_key'], $params['master_key']);
        ParseClient::setServerURL($params['server_url'], $params['mount_path']);
    }

    public function setHttpClient(SymfonyClient $symfonyClient = null) {
        if (null !== $symfonyClient) {
            ParseClient::setHttpClient($symfonyClient);
        }
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
    public function find($className, $id): ?object
    {
        return $this->getRepository($className)->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function persist($object): void
    {
        if (!is_object($object)) {
            throw new \InvalidArgumentException(gettype($object));
        }
        $this->unitOfWork->persist($object);
    }

    /**
     * {@inheritdoc}
     */
    public function remove($object): void
    {
        if (!is_object($object)) {
            throw new \InvalidArgumentException(gettype($object));
        }
        $this->unitOfWork->remove($object);
    }

    /**
     * {@inheritdoc}
     */
    public function merge($object): object
    {
        if (!is_object($object)) {
            throw new \InvalidArgumentException(gettype($object));
        }

        $this->unitOfWork->merge($object);

        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function clear($objectName = null): void
    {
        $this->unitOfWork->clear($objectName);
    }

    /**
     * {@inheritdoc}
     */
    public function detach($object): void
    {
        if (!is_object($object)) {
            throw new \InvalidArgumentException(gettype($object));
        }
        $this->unitOfWork->detach($object);
    }

    /**
     * {@inheritdoc}
     */
    public function refresh($object): void
    {
        if (!is_object($object)) {
            throw new \InvalidArgumentException(gettype($object));
        }
        $this->unitOfWork->refresh($object);
    }

    /**
     * {@inheritdoc}
     */
    public function flush($object = null): void
    {
        if (null !== $object && !is_object($object) && !is_array($object)) {
            throw new \InvalidArgumentException(gettype($object));
        }
        $this->unitOfWork->commit($object);
    }

    /**
     * @param string $className
     * 
     * @return ObjectRepository
     */
    public function getRepository($className)
    {
        return $this->repositoryFactory->getRepository($this, $className);
    }

    /**
     * @return ClassMetadata
     */
    public function getClassMetadata($className)
    {
        return $this->metadataFactory->getMetadataFor($className);
    }

    /**
     * @return ClassMetadataFactory
     */
    public function getMetadataFactory()
    {
        return $this->metadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function initializeObject($obj): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function contains($object): bool
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
    public function getReference(string $objectName, $id, ParseObject $data = null)
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

        $this->unitOfWork->registerManaged($object, $id, $data);

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
        return $this->config->getAlwaysMaster() || is_null(ParseUser::getCurrentUser());
    }

    public function getSchemaManager(): SchemaManager
    {
        return $this->schemaManager;
    }
}
