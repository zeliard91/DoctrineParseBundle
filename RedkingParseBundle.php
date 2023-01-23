<?php

namespace Redking\ParseBundle;

use Doctrine\Common\Proxy\AbstractProxyFactory;
use Redking\ParseBundle\DependencyInjection\Compiler\CacheCompatibilityPass;
use Redking\ParseBundle\DependencyInjection\Compiler\CreateHydratorDirectoryPass;
use Redking\ParseBundle\DependencyInjection\Compiler\CreateProxyDirectoryPass;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterEventListenersAndSubscribersPass;
use Redking\ParseBundle\DependencyInjection\Compiler\HWIOAuthPass;
use Redking\ParseBundle\DependencyInjection\Compiler\SessionCompilerPass;
use Redking\ParseBundle\DependencyInjection\RedkingParseExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class RedkingParseBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new CacheCompatibilityPass());
        $container->addCompilerPass(new RegisterEventListenersAndSubscribersPass('doctrine_parse.connections', 'redking_parse.event_manager', 'doctrine_parse'), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $container->addCompilerPass(new CreateProxyDirectoryPass(), PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new CreateHydratorDirectoryPass(), PassConfig::TYPE_BEFORE_REMOVING);

        $container->addCompilerPass(new HWIOAuthPass());
        $container->addCompilerPass(new SessionCompilerPass());
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new RedkingParseExtension();
    }
}
