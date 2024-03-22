<?php

namespace Redking\ParseBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Change services class
 * 
 */
class HWIOAuthPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['HWIOAuthBundle'])) {
            $definition = $container->getDefinition('hwi_oauth.user.provider.fosub_bridge.def');
            $definition->setClass('Redking\ParseBundle\Bridge\HWIOauth\UserProvider');
        }
    }
}
