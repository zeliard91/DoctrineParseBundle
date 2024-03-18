<?php

namespace Redking\ParseBundle\DependencyInjection\Compiler;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Inject session or request stack 
 */
class SessionCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $parseSessionDef = $container->getDefinition('doctrine_parse.session_storage');
        $sessionDef = null;
        try {
            $sessionDef = $container->getAlias('session');
        } catch (InvalidArgumentException $e) {
            try {
                $sessionDef = $container->getDefinition('session');
            } catch (ServiceNotFoundException $th) {}
        }

        if (null !== $sessionDef && !$sessionDef->isDeprecated()) {
            $parseSessionDef->addMethodCall('setSession', [$sessionDef]);
        }
    }
}