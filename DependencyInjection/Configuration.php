<?php

namespace Redking\ParseBundle\DependencyInjection;

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
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('redking_parse');

        $rootNode
            ->children()
                ->scalarNode('app_id')->isRequired()->end()
                ->scalarNode('rest_key')->isRequired()->end()
                ->scalarNode('master_key')->isRequired()->end()
                ->scalarNode('auto_mapping')->defaultFalse()->end()
                ->booleanNode('logging')->defaultValue('%kernel.debug%')->end()
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
                ->scalarNode('auto_generate_proxy_classes')->defaultValue(false)->end()
                ->scalarNode('hydrator_namespace')->defaultValue('Hydrators')->end()
                ->scalarNode('hydrator_dir')->defaultValue('%kernel.cache_dir%/doctrine/parse/Hydrators')->end()
                ->scalarNode('auto_generate_hydrator_classes')->defaultValue(false)->end()
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
            ->end()
        ;

        return $treeBuilder;
    }
}
