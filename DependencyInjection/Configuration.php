<?php

namespace Redking\ParseBundle\DependencyInjection;

use Redking\ParseBundle\Configuration as ParseBundleConfiguration;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('redking_parse');
        if (!method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->root('redking_parse');
        } else {
            $rootNode = $treeBuilder->getRootNode();
        }

        $rootNode
            ->children()
                ->scalarNode('app_id')->isRequired()->end()
                ->scalarNode('rest_key')->isRequired()->end()
                ->scalarNode('master_key')->isRequired()->end()
                ->scalarNode('server_url')->isRequired()->end()
                ->scalarNode('mount_path')->defaultValue('parse')->end()
                ->scalarNode('auto_mapping')->defaultFalse()->end()
                ->booleanNode('logging')->defaultValue('%kernel.debug%')->end()
                ->booleanNode('always_master')->defaultTrue()->end()
                ->arrayNode('profiler')
                    ->addDefaultsIfNotSet()
                    ->treatTrueLike(array('enabled' => true))
                    ->treatFalseLike(array('enabled' => false))
                    ->children()
                        ->booleanNode('enabled')->defaultValue('%kernel.debug%')->end()
                        ->booleanNode('pretty')->defaultValue('%kernel.debug%')->end()
                    ->end()
                ->end()
                ->scalarNode('proxy_namespace')->defaultValue('ParseProxies')->end()
                ->scalarNode('proxy_dir')->defaultValue('%kernel.cache_dir%/doctrine/parse/Proxies')->end()
                ->scalarNode('auto_generate_proxy_classes')
                    ->defaultValue(ParseBundleConfiguration::AUTOGENERATE_EVAL)
                    ->beforeNormalization()
                    ->always(static function ($v) {
                        if ($v === false) {
                            return ParseBundleConfiguration::AUTOGENERATE_EVAL;
                        }

                        if ($v === true) {
                            return ParseBundleConfiguration::AUTOGENERATE_FILE_NOT_EXISTS;
                        }

                        return $v;
                    })
                    ->end()
                ->end()
                ->scalarNode('hydrator_namespace')->defaultValue('Hydrators')->end()
                ->scalarNode('hydrator_dir')->defaultValue('%kernel.cache_dir%/doctrine/parse/Hydrators')->end()
                ->scalarNode('auto_generate_hydrator_classes')
                    ->defaultValue(ParseBundleConfiguration::AUTOGENERATE_NEVER)
                    ->beforeNormalization()
                    ->always(static function ($v) {
                        if ($v === false) {
                            return ParseBundleConfiguration::AUTOGENERATE_NEVER;
                        }

                        if ($v === true) {
                            return ParseBundleConfiguration::AUTOGENERATE_ALWAYS;
                        }

                        return $v;
                    })
                    ->end()
                ->end()
                ->scalarNode('fixture_loader')
                    ->defaultValue(ContainerAwareLoader::class)
                    ->beforeNormalization()
                        ->ifTrue(function($v) {return !($v == ContainerAwareLoader::class || in_array(ContainerAwareLoader::class, class_parents($v)));})
                        ->then(function($v) { throw new \LogicException(sprintf("The %s class is not a subclass of the ContainerAwareLoader", $v));})
                    ->end()
                ->end()
                ->arrayNode('mappings')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(function ($v) {
                                return array('type' => $v);
                            })
                        ->end()
                        ->treatNullLike(array())
                        ->treatFalseLike(array('mapping' => false))
                        ->performNoDeepMerging()
                        ->children()
                            ->scalarNode('mapping')->defaultValue(true)->end()
                            ->scalarNode('type')->end()
                            ->scalarNode('dir')->end()
                            ->scalarNode('alias')->end()
                            ->scalarNode('prefix')->end()
                            ->booleanNode('is_bundle')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('metadata_cache_driver')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function($v) { return ['type' => $v]; })
                    ->end()
                    ->children()
                        ->scalarNode('type')->defaultValue('array')->end()
                        ->scalarNode('class')->end()
                        ->scalarNode('host')->end()
                        ->integerNode('port')->end()
                        ->scalarNode('instance_class')->end()
                        ->scalarNode('id')->end()
                        ->scalarNode('namespace')->end()
                    ->end()
                ->end()
                ->append($this->getTargetObjectsResolverNode())
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * Return target objects resolver node
     *
     * @return \Symfony\Component\Config\Definition\Builder\NodeDefinition
     */
    private function getTargetObjectsResolverNode()
    {
        $treeBuilder = new TreeBuilder('resolve_target_objects');
        if (!method_exists($treeBuilder, 'getRootNode')) {
            $node = $treeBuilder->root('resolve_target_objects');
        } else {
            $node = $treeBuilder->getRootNode();
        }

        $node
            ->useAttributeAsKey('interface')
            ->prototype('scalar')
                ->cannotBeEmpty()
            ->end()
        ;

        return $node;
    }
}
