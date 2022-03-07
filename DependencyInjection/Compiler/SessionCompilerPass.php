<?php

namespace Redking\ParseBundle\DependencyInjection\Compiler;

use InvalidArgumentException;
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
        try {
            $sessionDef = $container->getAlias('session');
        } catch (InvalidArgumentException $e) {
            $sessionDef = $container->getDefinition('session');
        }

        if (!$sessionDef->isDeprecated()) {
            $parseSessionDef->addMethodCall('setSession', [$sessionDef]);
        }
    }
}