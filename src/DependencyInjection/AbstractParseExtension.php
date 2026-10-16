<?php

/*
 * The mapping and cache helpers below come from the Symfony
 * AbstractDoctrineExtension, deprecated in Symfony 7.4 for Doctrine bundles to
 * incorporate its code.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with Symfony.
 */

namespace Redking\ParseBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Groups the mapping driver and cache driver registration of the Parse object manager.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 *
 * @internal
 */
abstract class AbstractParseExtension extends Extension
{
    /**
     * Used inside metadata driver method to simplify aggregation of data.
     */
    protected array $aliasMap = [];

    /**
     * Used inside metadata driver method to simplify aggregation of data.
     */
    protected array $drivers = [];

    /**
     * @param array $objectManager A configured object manager
     *
     * @throws \InvalidArgumentException
     */
    protected function loadMappingInformation(array $objectManager, ContainerBuilder $container): void
    {
        if ($objectManager['auto_mapping']) {
            // automatically register bundle mappings
            foreach (array_keys($container->getParameter('kernel.bundles')) as $bundle) {
                if (!isset($objectManager['mappings'][$bundle])) {
                    $objectManager['mappings'][$bundle] = [
                        'mapping' => true,
                        'is_bundle' => true,
                    ];
                }
            }
        }

        foreach ($objectManager['mappings'] as $mappingName => $mappingConfig) {
            if (null !== $mappingConfig && false === $mappingConfig['mapping']) {
                continue;
            }

            $mappingConfig = array_replace([
                'dir' => false,
                'type' => false,
                'prefix' => false,
            ], (array) $mappingConfig);

            $mappingConfig['dir'] = $container->getParameterBag()->resolveValue($mappingConfig['dir']);
            // a bundle configuration is detected by realizing that the specified dir is not absolute and existing
            if (!isset($mappingConfig['is_bundle'])) {
                $mappingConfig['is_bundle'] = !is_dir($mappingConfig['dir']);
            }

            if ($mappingConfig['is_bundle']) {
                $bundle = null;
                $bundleMetadata = null;
                foreach ($container->getParameter('kernel.bundles') as $name => $class) {
                    if ($mappingName === $name) {
                        $bundle = new \ReflectionClass($class);
                        $bundleMetadata = $container->getParameter('kernel.bundles_metadata')[$name];

                        break;
                    }
                }

                if (null === $bundle) {
                    throw new \InvalidArgumentException(\sprintf('Bundle "%s" does not exist or it is not enabled.', $mappingName));
                }

                $mappingConfig = $this->getMappingDriverBundleConfigDefaults($mappingConfig, $bundle, $container, $bundleMetadata['path']);
                if (!$mappingConfig) {
                    continue;
                }
            } elseif (!$mappingConfig['type']) {
                $mappingConfig['type'] = 'attribute';
            }

            $this->assertValidMappingConfiguration($mappingConfig, $objectManager['name']);
            $this->setMappingDriverConfig($mappingConfig, $mappingName);
            $this->setMappingDriverAlias($mappingConfig, $mappingName);
        }
    }

    /**
     * Register the alias for this mapping driver.
     *
     * Aliases can be used in the Query languages of all the Doctrine object managers to simplify writing tasks.
     */
    protected function setMappingDriverAlias(array $mappingConfig, string $mappingName): void
    {
        if (isset($mappingConfig['alias'])) {
            $this->aliasMap[$mappingConfig['alias']] = $mappingConfig['prefix'];
        } else {
            $this->aliasMap[$mappingName] = $mappingConfig['prefix'];
        }
    }

    /**
     * Register the mapping driver configuration for later use with the object managers metadata driver chain.
     *
     * @throws \InvalidArgumentException
     */
    protected function setMappingDriverConfig(array $mappingConfig, string $mappingName): void
    {
        $mappingDirectory = $mappingConfig['dir'];
        if (!is_dir($mappingDirectory)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Doctrine mapping path given. Cannot load Doctrine mapping/bundle named "%s".', $mappingName));
        }

        $this->drivers[$mappingConfig['type']][$mappingConfig['prefix']] = realpath($mappingDirectory) ?: $mappingDirectory;
    }

    /**
     * If this is a bundle controlled mapping all the missing information can be autodetected by this method.
     *
     * Returns false when autodetection failed, an array of the completed information otherwise.
     */
    protected function getMappingDriverBundleConfigDefaults(array $bundleConfig, \ReflectionClass $bundle, ContainerBuilder $container, ?string $bundleDir = null): array|false
    {
        $bundleClassDir = \dirname($bundle->getFileName());
        $bundleDir ??= $bundleClassDir;

        if (!$bundleConfig['type']) {
            $bundleConfig['type'] = $this->detectMetadataDriver($bundleDir, $container);

            if (!$bundleConfig['type'] && $bundleDir !== $bundleClassDir) {
                $bundleConfig['type'] = $this->detectMetadataDriver($bundleClassDir, $container);
            }
        }

        if (!$bundleConfig['type']) {
            // skip this bundle, no mapping information was found.
            return false;
        }

        if (!$bundleConfig['dir']) {
            if (\in_array($bundleConfig['type'], ['staticphp', 'attribute'], true)) {
                $bundleConfig['dir'] = $bundleClassDir.'/'.$this->getMappingObjectDefaultName();
            } else {
                $bundleConfig['dir'] = $bundleDir.'/'.$this->getMappingResourceConfigDirectory($bundleDir);
            }
        } else {
            $bundleConfig['dir'] = $bundleDir.'/'.$bundleConfig['dir'];
        }

        if (!$bundleConfig['prefix']) {
            $bundleConfig['prefix'] = $bundle->getNamespaceName().'\\'.$this->getMappingObjectDefaultName();
        }

        return $bundleConfig;
    }

    /**
     * Register all the collected mapping information with the object manager by registering the appropriate mapping drivers.
     */
    protected function registerMappingDrivers(array $objectManager, ContainerBuilder $container): void
    {
        // configure metadata driver for each bundle based on the type of mapping files found
        if ($container->hasDefinition($this->getObjectManagerElementName($objectManager['name'].'_metadata_driver'))) {
            $chainDriverDef = $container->getDefinition($this->getObjectManagerElementName($objectManager['name'].'_metadata_driver'));
        } else {
            $chainDriverDef = new Definition($this->getMetadataDriverClass('driver_chain'));
        }

        foreach ($this->drivers as $driverType => $driverPaths) {
            $mappingService = $this->getObjectManagerElementName($objectManager['name'].'_'.$driverType.'_metadata_driver');
            if ($container->hasDefinition($mappingService)) {
                $mappingDriverDef = $container->getDefinition($mappingService);
                $args = $mappingDriverDef->getArguments();
                $args[0] = array_merge(array_values($driverPaths), $args[0]);
                $mappingDriverDef->setArguments($args);
            } else {
                $mappingDriverDef = new Definition($this->getMetadataDriverClass($driverType), [
                    array_values($driverPaths),
                ]);
            }
            if (str_contains($mappingDriverDef->getClass(), 'yml') || str_contains($mappingDriverDef->getClass(), 'xml')
                || str_contains($mappingDriverDef->getClass(), 'Yaml') || str_contains($mappingDriverDef->getClass(), 'Xml')
            ) {
                $mappingDriverDef->setArguments([array_flip($driverPaths)]);
                $mappingDriverDef->addMethodCall('setGlobalBasename', ['mapping']);
            }

            $container->setDefinition($mappingService, $mappingDriverDef);

            foreach ($driverPaths as $prefix => $driverPath) {
                $chainDriverDef->addMethodCall('addDriver', [new Reference($mappingService), $prefix]);
            }
        }

        $container->setDefinition($this->getObjectManagerElementName($objectManager['name'].'_metadata_driver'), $chainDriverDef);
    }

    /**
     * Assertion if the specified mapping information is valid.
     *
     * @throws \InvalidArgumentException
     */
    protected function assertValidMappingConfiguration(array $mappingConfig, string $objectManagerName): void
    {
        if (!$mappingConfig['type'] || !$mappingConfig['dir'] || !$mappingConfig['prefix']) {
            throw new \InvalidArgumentException(\sprintf('Mapping definitions for Doctrine manager "%s" require at least the "type", "dir" and "prefix" options.', $objectManagerName));
        }

        if (!is_dir($mappingConfig['dir'])) {
            throw new \InvalidArgumentException(\sprintf('Specified non-existing directory "%s" as Doctrine mapping source.', $mappingConfig['dir']));
        }

        if (!\in_array($mappingConfig['type'], ['xml', 'yml', 'php', 'staticphp', 'attribute'], true)) {
            throw new \InvalidArgumentException(\sprintf('Can only configure "xml", "yml", "php", "staticphp" or "attribute" through the DoctrineBundle. Use your own bundle to configure other metadata drivers. You can register them by adding a new driver to the "%s" service definition.', $this->getObjectManagerElementName($objectManagerName.'_metadata_driver')));
        }
    }

    /**
     * Detects what metadata driver to use for the supplied directory.
     */
    protected function detectMetadataDriver(string $dir, ContainerBuilder $container): ?string
    {
        $configPath = $this->getMappingResourceConfigDirectory($dir);
        $extension = $this->getMappingResourceExtension();

        if (glob($dir.'/'.$configPath.'/*.'.$extension.'.xml', \GLOB_NOSORT)) {
            $driver = 'xml';
        } elseif (glob($dir.'/'.$configPath.'/*.'.$extension.'.yml', \GLOB_NOSORT)) {
            $driver = 'yml';
        } elseif (glob($dir.'/'.$configPath.'/*.'.$extension.'.php', \GLOB_NOSORT)) {
            $driver = 'php';
        } else {
            // add the closest existing directory as a resource
            $resource = $dir.'/'.$configPath;
            while (!is_dir($resource)) {
                $resource = \dirname($resource);
            }
            $container->fileExists($resource, false);

            if ($container->fileExists($dir.'/'.$this->getMappingObjectDefaultName(), false)) {
                return 'attribute';
            }

            return null;
        }
        $container->fileExists($dir.'/'.$configPath, false);

        return $driver;
    }

    /**
     * Loads a configured object manager metadata, query or result cache driver.
     *
     * @throws \InvalidArgumentException in case of unknown driver type
     */
    protected function loadObjectManagerCacheDriver(array $objectManager, ContainerBuilder $container, string $cacheName): void
    {
        $this->loadCacheDriver($cacheName, $objectManager['name'], $objectManager[$cacheName.'_driver'], $container);
    }

    abstract protected function loadCacheDriver(string $cacheName, string $objectManagerName, array $cacheDriver, ContainerBuilder $container): string;

    /**
     * Prefixes the given name with the object manager service namespace.
     */
    abstract protected function getObjectManagerElementName(string $name): string;

    /**
     * Noun that describes the mapped objects such as Entity or Document.
     */
    abstract protected function getMappingObjectDefaultName(): string;

    /**
     * Relative path from the bundle root to the directory where mapping files reside.
     */
    abstract protected function getMappingResourceConfigDirectory(?string $bundleDir = null): string;

    /**
     * Extension used by the mapping files.
     */
    abstract protected function getMappingResourceExtension(): string;

    /**
     * The class name used by the various mapping drivers.
     */
    abstract protected function getMetadataDriverClass(string $driverType): string;
}
