<?php

namespace Redking\ParseBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterEventListenersAndSubscribersPass;
use Redking\ParseBundle\DependencyInjection\Compiler\HWIOAuthPass;

class RedkingParseBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new RegisterEventListenersAndSubscribersPass('doctrine_parse.connections', 'redking_parse.event_manager', 'doctrine_parse'), PassConfig::TYPE_BEFORE_OPTIMIZATION);

        $container->addCompilerPass(new HWIOAuthPass());
    }
}
