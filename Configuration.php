<?php

namespace Redking\ParseBundle;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Psr\Cache\CacheItemPoolInterface;
use Redking\ParseBundle\Exception\RedkingParseException;
use Redking\ParseBundle\Mapping\DefaultNamingStrategy;
use Redking\ParseBundle\Mapping\DefaultObjectListenerResolver;

/**
 * Configuration container for the Doctrine DBAL.
 *
 * @since    2.0
 *
 * @author   Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author   Jonathan Wage <jonwage@gmail.com>
 * @author   Roman Borschel <roman@code-factory.org>
 *
 * @internal When adding a new configuration option just write a getter/setter
 *           pair and add the option to the _attributes array with a proper default value.
 */
class Configuration
{
    /**
     * Array of attributes for this configuration instance.
     *
     * @var array
     */
    protected $_attributes = array();

    /** @var CacheItemPoolInterface|null */
    private $metadataCache;

    /**
     * Adds a namespace under a certain alias.
     *
     * @param string $alias
     * @param string $namespace
     */
    public function addEntityNamespace($alias, $namespace)
    {
        $this->_attributes['entityNamespaces'][$alias] = $namespace;
    }

    /**
     * Resolves a registered namespace alias to the full namespace.
     *
     * @param string $entityNamespaceAlias
     *
     * @return string
     *
     * @throws ORMException
     */
    public function getEntityNamespace($entityNamespaceAlias)
    {
        if (!isset($this->_attributes['entityNamespaces'][$entityNamespaceAlias])) {
            throw new RedkingParseException("Unknown Entity namespace alias '$entityNamespaceAlias'.");
        }

        return trim($this->_attributes['entityNamespaces'][$entityNamespaceAlias], '\\');
    }

    /**
     * Sets the entity alias map.
     *
     * @param array $entityNamespaces
     */
    public function setEntityNamespaces(array $entityNamespaces)
    {
        $this->_attributes['entityNamespaces'] = $entityNamespaces;
    }

    /**
     * Retrieves the list of registered entity namespace aliases.
     *
     * @return array
     */
    public function getEntityNamespaces()
    {
        return $this->_attributes['entityNamespaces'];
    }

    /**
     * Sets the cache driver implementation that is used for metadata caching.
     *
     * @param MappingDriver $driverImpl
     *
     * @todo Force parameter to be a Closure to ensure lazy evaluation
     *       (as soon as a metadata cache is in effect, the driver never needs to initialize).
     */
    public function setMetadataDriverImpl(MappingDriver $driverImpl)
    {
        $this->_attributes['metadataDriverImpl'] = $driverImpl;
    }

    /**
     * Gets the cache driver implementation that is used for the mapping metadata.
     *
     * @return MappingDriver|null
     *
     * @throws ORMException
     */
    public function getMetadataDriverImpl()
    {
        return isset($this->_attributes['metadataDriverImpl'])
            ? $this->_attributes['metadataDriverImpl']
            : null;
    }

    /**
     * Sets naming strategy.
     *
     * @since 2.3
     *
     * @param DefaultNamingStrategy $namingStrategy
     */
    public function setNamingStrategy(DefaultNamingStrategy $namingStrategy)
    {
        $this->_attributes['namingStrategy'] = $namingStrategy;
    }

    /**
     * Gets naming strategy..
     *
     * @since 2.3
     *
     * @return NamingStrategy
     */
    public function getNamingStrategy()
    {
        if (!isset($this->_attributes['namingStrategy'])) {
            $this->_attributes['namingStrategy'] = new DefaultNamingStrategy();
        }

        return $this->_attributes['namingStrategy'];
    }

    /**
     * Sets the directory where Doctrine generates any necessary proxy class files.
     *
     * @param string $dir
     */
    public function setProxyDir($dir)
    {
        $this->_attributes['proxyDir'] = $dir;
    }

    /**
     * Gets the directory where Doctrine generates any necessary proxy class files.
     *
     * @return string
     */
    public function getProxyDir()
    {
        return isset($this->_attributes['proxyDir']) ?
            $this->_attributes['proxyDir'] : null;
    }

    /**
     * Gets the namespace where proxy classes reside.
     *
     * @return string
     */
    public function getProxyNamespace()
    {
        return isset($this->_attributes['proxyNamespace']) ?
            $this->_attributes['proxyNamespace'] : null;
    }

    /**
     * Sets the namespace where proxy classes reside.
     *
     * @param string $ns
     */
    public function setProxyNamespace($ns)
    {
        $this->_attributes['proxyNamespace'] = $ns;
    }

    /**
     * Gets a boolean flag that indicates whether proxy classes should always be regenerated
     * during each script execution.
     *
     * @return bool|int
     */
    public function getAutoGenerateProxyClasses()
    {
        return isset($this->_attributes['autoGenerateProxyClasses'])
            ? $this->_attributes['autoGenerateProxyClasses']
            : AbstractProxyFactory::AUTOGENERATE_ALWAYS;
    }

    /**
     * Sets a boolean flag that indicates whether proxy classes should always be regenerated
     * during each script execution.
     *
     * @param bool|int $bool Possible values are constants of Doctrine\Common\Proxy\AbstractProxyFactory
     */
    public function setAutoGenerateProxyClasses($bool)
    {
        $this->_attributes['autoGenerateProxyClasses'] = $bool;
    }

    /**
     * Gets the logger callable.
     *
     * @return callable
     */
    public function getLoggerCallable()
    {
        return isset($this->_attributes['loggerCallable']) ? $this->_attributes['loggerCallable'] : null;
    }

    /**
     * Set the logger callable.
     *
     * @param callable $loggerCallable
     */
    public function setLoggerCallable($loggerCallable)
    {
        $this->_attributes['loggerCallable'] = $loggerCallable;
    }

    /**
     * Gets the profiler callable.
     *
     * @return callable
     */
    public function getProfilerCallable()
    {
        return isset($this->_attributes['profilerCallable']) ? $this->_attributes['profilerCallable'] : null;
    }

    /**
     * Set the profiler callable.
     *
     * @param callable $loggerCallable
     */
    public function setProfilerCallable($profilerCallable)
    {
        $this->_attributes['profilerCallable'] = $profilerCallable;
    }

    /**
     * Set the entity listener resolver.
     *
     * @since 2.4
     *
     * @param DefaultObjectListenerResolver $resolver
     */
    public function setObjectListenerResolver(DefaultObjectListenerResolver $resolver)
    {
        $this->_attributes['ObjectListenerResolver'] = $resolver;
    }

    /**
     * Get the entity listener resolver.
     *
     * @since 2.4
     *
     * @return DefaultObjectListenerResolver
     */
    public function getObjectListenerResolver()
    {
        if (!isset($this->_attributes['ObjectListenerResolver'])) {
            $this->_attributes['ObjectListenerResolver'] = new DefaultObjectListenerResolver();
        }

        return $this->_attributes['ObjectListenerResolver'];
    }

    /**
     * Define Parse connection parameters.
     *
     * @param array $parameters
     */
    public function setConnectionParameters(array $parameters)
    {
        $this->_attributes['connectionParams'] = $parameters;
    }

    /**
     * Get Parse connection parameters.
     *
     * @return array
     */
    public function getConnectionParameters()
    {
        if (!isset($this->_attributes['connectionParams'])) {
            throw new \Exception("Connection parameters must be defined.");
        }

        return $this->_attributes['connectionParams'];
    }

    /**
     * Gets the alwaysMaster.
     *
     * @return boolean
     */
    public function getAlwaysMaster()
    {
        return isset($this->_attributes['alwaysMaster']) ? $this->_attributes['alwaysMaster'] : true;
    }

    /**
     * Set the alwaysMaster.
     *
     * @param boolean $alwaysMaster
     */
    public function setAlwaysMaster($alwaysMaster)
    {
        $this->_attributes['alwaysMaster'] = $alwaysMaster;
    }

    /**
     * Gets the cache driver implementation that is used for metadata caching.
     *
     * @return \Doctrine\Common\Cache\Cache
     */
    public function getMetadataCacheImpl()
    {
        return isset($this->attributes['metadataCacheImpl']) ? $this->attributes['metadataCacheImpl'] : null;
    }

    /**
     * Sets the cache driver implementation that is used for metadata caching.
     *
     * @param \Doctrine\Common\Cache\Cache $cacheImpl
     */
    public function setMetadataCacheImpl(Cache $cacheImpl)
    {
        $this->attributes['metadataCacheImpl'] = $cacheImpl;
    }

    public function getMetadataCache(): ?CacheItemPoolInterface
    {
        return $this->metadataCache;
    }

    public function setMetadataCache(CacheItemPoolInterface $cache): void
    {
        $this->metadataCache                   = $cache;
        $this->attributes['metadataCacheImpl'] = DoctrineProvider::wrap($cache);
    }
}
