<?php

namespace Redking\ParseBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Bridge\Doctrine\DependencyInjection\AbstractDoctrineExtension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class RedkingParseExtension extends AbstractDoctrineExtension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Define dummy doctrine_parse.connections in order to be compatible with doctrine event listener compiler pass
        $connections = [];
        $connections[] = [
            'app_id' => $config['app_id'],
            'rest_key' => $config['rest_key'],
            'master_key' => $config['master_key'],
            'server_url' => $config['server_url'],
            'mount_path' => $config['mount_path'],
        ];
        $container->setParameter('doctrine_parse.connections', $connections);


        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('form.yml');

        $configDef = $container->getDefinition('redking_parse.configuration');

        // cheat $config for parent requirements
        $config['name'] = 'default';
        $this->loadEntityManagerMappingInformation($config, $configDef, $container);
        $this->resolveDocuments($config, $container);

        $container->setParameter('doctrine.parse.auto_generate_proxy_classes', $config['auto_generate_proxy_classes']);

        $methods = array(
            // 'setMetadataCacheImpl' => new Reference(sprintf('doctrine.parse.%s_metadata_cache', $config['name'])),
            // 'setQueryCacheImpl' => new Reference(sprintf('doctrine.parse.%s_query_cache', $config['name'])),
            // 'setResultCacheImpl' => new Reference(sprintf('doctrine.parse.%s_result_cache', $config['name'])),
            'setMetadataDriverImpl' => new Reference('doctrine.parse.'.$config['name'].'_metadata_driver'),
            'setProxyDir' => $config['proxy_dir'],
            'setProxyNamespace' => $config['proxy_namespace'],
            'setAutoGenerateProxyClasses' => $config['auto_generate_proxy_classes'],
            // 'setClassMetadataFactoryName' => $entityManager['class_metadata_factory_name'],
            // 'setDefaultRepositoryClassName' => $entityManager['default_repository_class'],
            'setConnectionParameters' => $connections[0],
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
                ->addTag('data_collector', array('id' => 'parse', 'template' => 'RedkingParseBundle:Collector:parse'))
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
            $configDef->addMethodCall($method, array($arg));
        }

        // set the fixtures loader
        $container->setParameter('doctrine_parse.fixture_loader', $config['fixture_loader']);

        // Load bridge configurations
        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['FOSUserBundle'])) {
            $loader->load('bridge/fosuser.yml');
        }

        $container->setAlias('doctrine.parse.object_manager', 'redking_parse.manager');
    }

    protected function getObjectManagerElementName($name)
    {
        return 'doctrine.parse.'.$name;
    }

    protected function getMappingObjectDefaultName()
    {
        return 'ParseObject';
    }

    /**
     * {@inheritdoc}
     */
    protected function getMappingResourceConfigDirectory()
    {
        return 'Resources/config/doctrine';
    }

    /**
     * {@inheritdoc}
     */
    protected function getMappingResourceExtension()
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
}
