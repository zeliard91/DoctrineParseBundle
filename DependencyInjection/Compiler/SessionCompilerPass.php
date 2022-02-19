<?php

namespace Redking\ParseBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Inject session or request stack 
 */
class SessionCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $parseSessionDef = $container->getDefinition('doctrine_parse.session_storage');
        $sessionDef = $container->getAlias('session');

        if (!$sessionDef->isDeprecated()) {
            $parseSessionDef->addMethodCall('setSession', [$sessionDef]);
        }
    }
}