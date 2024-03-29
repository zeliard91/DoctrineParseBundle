<?php

/*
 * This file is part of the Doctrine MongoDBBundle
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Redking\ParseBundle\DependencyInjection\Compiler;

use Doctrine\Persistence\Mapping\Driver\SymfonyFileLocator;
use Redking\ParseBundle\Mapping\Driver\AnnotationDriver;
use Redking\ParseBundle\Mapping\Driver\YamlDriver;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterMappingsPass;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class for Symfony bundles to configure mappings for model classes not in the
 * automapped folder.
 *
 * NOTE: alias is only supported by Symfony 2.6+ and will be ignored with older versions.
 *
 * @author David Buchmann <mail@davidbu.ch>
 */
class DoctrineParseMappingsPass extends RegisterMappingsPass
{
    /**
     * You should not directly instantiate this class but use one of the
     * factory methods.
     *
     * @param Definition|Reference $driver            Driver DI definition or reference.
     * @param array                $namespaces        List of namespaces handled by $driver.
     * @param string[]             $managerParameters Ordered list of container parameters that
     *                                                could hold the manager name.
     *                                                doctrine.default_entity_manager is appended
     *                                                automatically.
     * @param string|false         $enabledParameter  If specified, the compiler pass only executes
     *                                                if this parameter is defined in the service
     *                                                container.
     * @param array                $aliasMap          Map of alias to namespace.
     */
    public function __construct($driver, array $namespaces, array $managerParameters, $enabledParameter = false, array $aliasMap = array())
    {
        $managerParameters[] = 'redking_parse';
        parent::__construct(
            $driver,
            $namespaces,
            $managerParameters,
            'doctrine.parse.%s_metadata_driver',
            $enabledParameter,
            'doctrine.parse.%s_configuration',
            'addEntityNamespace',
            $aliasMap
        );
    }

    /**
     * @param array        $namespaces        Hashmap of directory path to namespace.
     * @param string[]     $managerParameters List of parameters that could which object manager name
     *                                        your bundle uses. This compiler pass will automatically
     *                                        append the parameter name for the default entity manager
     *                                        to this list.
     * @param string|false $enabledParameter  Service container parameter that must be present to
     *                                        enable the mapping. Set to false to not do any check,
     *                                        optional.
     * @param string[]     $aliasMap          Map of alias to namespace.
     *
     * @return self
     */
    /*public static function createXmlMappingDriver(array $namespaces, array $managerParameters = array(), $enabledParameter = false, array $aliasMap = array())
    {
        $arguments = array($namespaces, '.orm.xml');
        $locator = new Definition('Doctrine\Persistence\Mapping\Driver\SymfonyFileLocator', $arguments);
        $driver = new Definition('Doctrine\ORM\Mapping\Driver\XmlDriver', array($locator));

        return new DoctrineOrmMappingsPass($driver, $namespaces, $managerParameters, $enabledParameter, $aliasMap);
    }*/

    /**
     * @param array        $namespaces        Hashmap of directory path to namespace
     * @param string[]     $managerParameters List of parameters that could which object manager name
     *                                        your bundle uses. This compiler pass will automatically
     *                                        append the parameter name for the default entity manager
     *                                        to this list.
     * @param string|false $enabledParameter  Service container parameter that must be present to
     *                                        enable the mapping. Set to false to not do any check,
     *                                        optional.
     * @param string[]     $aliasMap          Map of alias to namespace.
     *
     * @return self
     */
    public static function createYamlMappingDriver(array $namespaces, array $managerParameters = array(), $enabledParameter = false, array $aliasMap = array())
    {
        $arguments = array($namespaces, '.parse.yml');
        $locator = new Definition(SymfonyFileLocator::class, $arguments);
        $driver = new Definition(YamlDriver::class, array($locator));

        return new self($driver, $namespaces, $managerParameters, $enabledParameter, $aliasMap);
    }

    /*
     * @param array    $namespaces        Hashmap of directory path to namespace
     * @param string[] $managerParameters List of parameters that could which object manager name
     *                                    your bundle uses. This compiler pass will automatically
     *                                    append the parameter name for the default entity manager
     *                                    to this list.
     * @param string   $enabledParameter  Service container parameter that must be present to
     *                                    enable the mapping. Set to false to not do any check,
     *                                    optional.
     * @param string[] $aliasMap          Map of alias to namespace.
     *
     * @return self
     */
    /*public static function createPhpMappingDriver(array $namespaces, array $managerParameters = array(), $enabledParameter = false, array $aliasMap = array())
    {
        $arguments = array($namespaces, '.php');
        $locator = new Definition('Doctrine\Persistence\Mapping\Driver\SymfonyFileLocator', $arguments);
        $driver = new Definition('Doctrine\Persistence\Mapping\Driver\PHPDriver', array($locator));

        return new DoctrineOrmMappingsPass($driver, $namespaces, $managerParameters, $enabledParameter, $aliasMap);
    }*/

    /*
     * @param array    $namespaces        List of namespaces that are handled with annotation mapping
     * @param array    $directories       List of directories to look for annotated classes
     * @param string[] $managerParameters List of parameters that could which object manager name
     *                                    your bundle uses. This compiler pass will automatically
     *                                    append the parameter name for the default entity manager
     *                                    to this list.
     * @param string|false   $enabledParameter  Service container parameter that must be present to
     *                                    enable the mapping. Set to false to not do any check,
     *                                    optional.
     * @param string[] $aliasMap          Map of alias to namespace.
     *
     * @return self
     */
    public static function createAnnotationMappingDriver(array $namespaces, array $directories, array $managerParameters = array(), $enabledParameter = false, array $aliasMap = array())
    {
        $driver = new Definition(AnnotationDriver::class, [new Reference('annotation_reader'), $directories]);

        return new self($driver, $namespaces, $managerParameters, $enabledParameter, $aliasMap);
    }

    /*
     * @param array    $namespaces        List of namespaces that are handled with static php mapping
     * @param array    $directories       List of directories to look for static php mapping files
     * @param string[] $managerParameters List of parameters that could which object manager name
     *                                    your bundle uses. This compiler pass will automatically
     *                                    append the parameter name for the default entity manager
     *                                    to this list.
     * @param string|false   $enabledParameter  Service container parameter that must be present to
     *                                    enable the mapping. Set to false to not do any check,
     *                                    optional.
     * @param string[] $aliasMap          Map of alias to namespace.
     *
     * @return self
     */
    /*public static function createStaticPhpMappingDriver(array $namespaces, array $directories, array $managerParameters = array(), $enabledParameter = false, array $aliasMap = array())
    {
        $driver = new Definition('Doctrine\Common\Persistence\Mapping\Driver\StaticPHPDriver', array($directories));

        return new DoctrineOrmMappingsPass($driver, $namespaces, $managerParameters, $enabledParameter, $aliasMap);
    }*/
}
