<?php

namespace Redking\ParseBundle\DependencyInjection;

use Doctrine\Common\Cache\MemcacheCache;
use Doctrine\Common\Cache\RedisCache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Bridge\Doctrine\DependencyInjection\AbstractDoctrineExtension;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class RedkingParseExtension extends AbstractDoctrineExtension
{
    /** @internal */
    public const CONFIGURATION_TAG = 'doctrine.parse.configuration';

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Define dummy doctrine_parse.connections in order to be compatible with doctrine event listener compiler pass
        $connectionParameters = [
            'app_id' => $config['app_id'],
            'rest_key' => $config['rest_key'],
            'master_key' => $config['master_key'],
            'server_url' => $config['server_url'],
            'mount_path' => $config['mount_path'],
        ];

        $connections = [];
        $connections['redking_parse.manager'] = [
            'redking_parse.manager',
        ];
        $container->setParameter('doctrine_parse.connections', $connections);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('form.yml');

        // set some options as parameters and unset them
        $config = $this->overrideParameters($config, $container);

        $configurationId = sprintf('doctrine_parse.%s_configuration', 'default');
        $configDef = new Definition('Redking\ParseBundle\Configuration');
        $configDef->addTag(self::CONFIGURATION_TAG);
        $configDef->setPublic(true);
        $container->setDefinition(
            $configurationId,
            $configDef
        );

        // cheat $config for parent requirements
        $config['name'] = 'default';
        $this->loadEntityManagerMappingInformation($config, $configDef, $container);
        $this->resolveDocuments($config, $container);

        $this->loadObjectManagerCacheDriver($config, $container, 'metadata_cache');

        $methods = array(
            'setMetadataCache' => new Reference(sprintf('doctrine.parse.%s_metadata_cache', $config['name'])),
            // 'setQueryCacheImpl' => new Reference(sprintf('doctrine.parse.%s_query_cache', $config['name'])),
            // 'setResultCacheImpl' => new Reference(sprintf('doctrine.parse.%s_result_cache', $config['name'])),
            'setMetadataDriverImpl' => new Reference('doctrine.parse.'.$config['name'].'_metadata_driver'),
            'setProxyDir' => '%doctrine_parse.proxy_dir%',
            'setProxyNamespace' => '%doctrine_parse.proxy_namespace%',
            'setAutoGenerateProxyClasses' => '%doctrine_parse.auto_generate_proxy_classes%',
            // 'setClassMetadataFactoryName' => $entityManager['class_metadata_factory_name'],
            // 'setDefaultRepositoryClassName' => $entityManager['default_repository_class'],
            'setConnectionParameters' => $connectionParameters,
            'setAlwaysMaster' => $config['always_master'],
        );

        // logging
        $loggers = array();
        if ($container->getParameterBag()->resolveValue($config['logging'])) {
            $loggers[] = new Reference('redking_parse.logger');
        }

        // profiler
        if ($container->getParameterBag()->resolveValue($config['profiler']['enabled'])) {
            $dataCollectorId = 'redking_parse.data_collector';
            $loggers[] = new Reference($dataCollectorId);
            $methods['setProfilerCallable'] = array(new Reference($dataCollectorId), 'startQuery');
            $container
                ->getDefinition($dataCollectorId)
                ->addTag('data_collector', array('id' => 'parse', 'template' => '@RedkingParse/Collector/parse'))
            ;
        }

        if (1 < count($loggers)) {
            $methods['setLoggerCallable'] = array(new Reference('redking_parse.logger.aggregate'), 'logQuery');
            $container
                ->getDefinition('redking_parse.logger.aggregate')
                ->addArgument($loggers)
            ;
        } elseif ($loggers) {
            $methods['setLoggerCallable'] = array($loggers[0], 'logQuery');
        }

        foreach ($methods as $method => $arg) {
            if ($configDef->hasMethodCall($method)) {
                $configDef->removeMethodCall($method);
            }

            $configDef->addMethodCall($method, array($arg));
        }

        // set the fixtures loader
        $definition = new Definition($config['fixture_loader'], [
            new Reference('service_container')
        ]);
        $container->setDefinition('doctrine_parse.fixture_loader', $definition);
        $container->setAlias($config['fixture_loader'], 'doctrine_parse.fixture_loader');

        // Load bridge configurations
        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['FOSUserBundle'])) {
            $loader->load('bridge/fosuser/email.yml');
            if (interface_exists('FOS\\UserBundle\\Util\\PasswordUpdaterInterface')) {
                $loader->load('bridge/fosuser/managerV2.yml');
            } else {
                $loader->load('bridge/fosuser/managerV1.yml');
            }
        }

        $container->setDefinition(
            'redking_parse.event_manager',
            new ChildDefinition('doctrine.parse.connection.event_manager')
        );

        $omArgs = [
            new Reference($configurationId),
            new Reference('redking_parse.event_manager'),
            new Reference('doctrine_parse.session_storage'),
        ];
        $omDef = new Definition('Redking\ParseBundle\ObjectManager', $omArgs);
        $omDef->setFactory(['Redking\ParseBundle\ObjectManager', 'create']);
        $omDef->addTag('doctrine_parse.object_manager');
        $omDef->setPublic(true);
        $container->setDefinition('redking_parse.manager', $omDef);

        $container->setAlias('doctrine.parse.object_manager', 'redking_parse.manager');
        $container->getAlias('doctrine.parse.object_manager')->setPublic(true);
    }

    /**
     * Uses some of the extension options to override DI extension parameters.
     *
     * @param array            $options   The available configuration options
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @return array<string, mixed>
     */
    protected function overrideParameters($options, ContainerBuilder $container)
    {
        $overrides = [
            'proxy_namespace',
            'proxy_dir',
            'auto_generate_proxy_classes',
            'hydrator_namespace',
            'hydrator_dir',
            'auto_generate_hydrator_classes',
            'default_commit_options',
            'persistent_collection_dir',
            'persistent_collection_namespace',
            'auto_generate_persistent_collection_classes',
        ];

        foreach ($overrides as $key) {
            if (! isset($options[$key])) {
                continue;
            }

            $container->setParameter('doctrine_parse.' . $key, $options[$key]);

            // the option should not be used, the parameter should be referenced
            unset($options[$key]);
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    protected function getObjectManagerElementName($name): string
    {
        return 'doctrine.parse.'.$name;
    }

    /**
     * {@inheritdoc}
     */
    protected function getMappingObjectDefaultName(): string
    {
        return 'ParseObject';
    }

    /**
     * {@inheritdoc}
     */
    protected function getMappingResourceConfigDirectory(string $bundleDir = null): string
    {
        return 'Resources/config/doctrine';
    }

    /**
     * {@inheritdoc}
     */
    protected function getMappingResourceExtension(): string
    {
        return 'parse';
    }

    /**
     * Loads an entity managers bundle mapping information.
     *
     * There are two distinct configuration possibilities for mapping information:
     *
     * 1. Specify a bundle and optionally details where the entity and mapping information reside.
     * 2. Specify an arbitrary mapping location.
     *
     * @example
     *
     *  doctrine.orm:
     *     mappings:
     *         MyBundle1: ~
     *         MyBundle2: yml
     *         MyBundle3: { type: annotation, dir: Entities/ }
     *         MyBundle4: { type: xml, dir: Resources/config/doctrine/mapping }
     *         MyBundle5:
     *             type: yml
     *             dir: bundle-mappings/
     *             alias: BundleAlias
     *         arbitrary_key:
     *             type: xml
     *             dir: %kernel.root_dir%/../src/vendor/DoctrineExtensions/lib/DoctrineExtensions/Entities
     *             prefix: DoctrineExtensions\Entities\
     *             alias: DExt
     *
     * In the case of bundles everything is really optional (which leads to autodetection for this bundle) but
     * in the mappings key everything except alias is a required argument.
     *
     * @param array            $entityManager A configured ORM entity manager
     * @param Definition       $ormConfigDef  A Definition instance
     * @param ContainerBuilder $container     A ContainerBuilder instance
     */
    protected function loadEntityManagerMappingInformation(array $entityManager, Definition $ormConfigDef, ContainerBuilder $container)
    {
        // reset state of drivers and alias map. They are only used by this methods and children.
        $this->drivers = array();
        $this->aliasMap = array();

        $this->loadMappingInformation($entityManager, $container);
        $this->registerMappingDrivers($entityManager, $container);

        $ormConfigDef->addMethodCall('setEntityNamespaces', array($this->aliasMap));
    }

    /**
     * Resolve document targets
     *
     * @param array $config Configuration
     */
    protected function resolveDocuments(array $config, ContainerBuilder $container)
    {
        if ($config['resolve_target_objects']) {
            $def = $container->findDefinition('doctrine.parse.listeners.resolve_target_object');
            foreach ($config['resolve_target_objects'] as $name => $implementation) {
                $def->addMethodCall('addResolveObject', array(
                    $name, $implementation, array(),
                ));
            }
            $def->addTag('doctrine_parse.event_subscriber');
        }
    }

    /**
     * The class name used by the various mapping drivers.
     */
    protected function getMetadataDriverClass(string $driverType): string
    {
        return '%'.$this->getObjectManagerElementName('metadata.'.$driverType.'.class%');
    }

    /**
     * Loads a cache driver.
     *
     * @param string $cacheName         The cache driver name
     * @param string $objectManagerName The object manager name
     * @param array  $cacheDriver       The cache driver mapping
     *
     * @throws InvalidArgumentException
     *
     * @psalm-suppress UndefinedClass this won't be necessary when removing metadata cache configuration
     */
    protected function loadCacheDriver($cacheName, $objectManagerName, array $cacheDriver, ContainerBuilder $container): string
    {
        if (isset($cacheDriver['namespace'])) {
            return parent::loadCacheDriver($cacheName, $objectManagerName, $cacheDriver, $container);
        }

        $cacheDriverServiceId = $this->getObjectManagerElementName($objectManagerName . '_' . $cacheName);

        switch ($cacheDriver['type']) {
            case 'service':
                $container->setAlias($cacheDriverServiceId, new Alias($cacheDriver['id'], false));

                return $cacheDriverServiceId;

            case 'memcached':
                if (! empty($cacheDriver['class']) && $cacheDriver['class'] !== MemcacheCache::class) {
                    return parent::loadCacheDriver($cacheName, $objectManagerName, $cacheDriver, $container);
                }

                $memcachedInstanceClass = ! empty($cacheDriver['instance_class']) ? $cacheDriver['instance_class'] : '%' . $this->getObjectManagerElementName('cache.memcached_instance.class') . '%';
                $memcachedHost          = ! empty($cacheDriver['host']) ? $cacheDriver['host'] : '%' . $this->getObjectManagerElementName('cache.memcached_host') . '%';
                $memcachedPort          = ! empty($cacheDriver['port']) ? $cacheDriver['port'] : '%' . $this->getObjectManagerElementName('cache.memcached_port') . '%';
                $memcachedInstance      = new Definition($memcachedInstanceClass);
                $memcachedInstance->addMethodCall('addServer', [
                    $memcachedHost,
                    $memcachedPort,
                ]);
                $container->setDefinition($this->getObjectManagerElementName(sprintf('%s_memcached_instance', $objectManagerName)), $memcachedInstance);

                $cacheDef = new Definition(MemcachedAdapter::class, [new Reference($this->getObjectManagerElementName(sprintf('%s_memcached_instance', $objectManagerName)))]);

                break;

            case 'redis':
                if (! empty($cacheDriver['class']) && $cacheDriver['class'] !== RedisCache::class) {
                    return parent::loadCacheDriver($cacheName, $objectManagerName, $cacheDriver, $container);
                }

                $redisInstanceClass = ! empty($cacheDriver['instance_class']) ? $cacheDriver['instance_class'] : '%' . $this->getObjectManagerElementName('cache.redis_instance.class') . '%';
                $redisHost          = ! empty($cacheDriver['host']) ? $cacheDriver['host'] : '%' . $this->getObjectManagerElementName('cache.redis_host') . '%';
                $redisPort          = ! empty($cacheDriver['port']) ? $cacheDriver['port'] : '%' . $this->getObjectManagerElementName('cache.redis_port') . '%';
                $redisInstance      = new Definition($redisInstanceClass);
                $redisInstance->addMethodCall('connect', [
                    $redisHost,
                    $redisPort,
                ]);
                $container->setDefinition($this->getObjectManagerElementName(sprintf('%s_redis_instance', $objectManagerName)), $redisInstance);

                $cacheDef = new Definition(RedisAdapter::class, [new Reference($this->getObjectManagerElementName(sprintf('%s_redis_instance', $objectManagerName)))]);

                break;

            case 'apcu':
                $cacheDef = new Definition(ApcuAdapter::class);

                break;

            case 'array':
                $cacheDef = new Definition(ArrayAdapter::class);

                break;

            default:
                return parent::loadCacheDriver($cacheName, $objectManagerName, $cacheDriver, $container);
        }

        $cacheDef->setPublic(false);
        $container->setDefinition($cacheDriverServiceId, $cacheDef);

        return $cacheDriverServiceId;
    }
}
