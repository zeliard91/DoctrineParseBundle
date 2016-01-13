<?php

namespace Redking\ParseBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterEventListenersAndSubscribersPass;
use Redking\ParseBundle\DependencyInjection\Compiler\HWIOAuthPass;
use Parse\ParseClient;

class RedkingParseBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new RegisterEventListenersAndSubscribersPass('doctrine.connections', 'redking_parse.event_manager', 'doctrine_parse'), PassConfig::TYPE_BEFORE_OPTIMIZATION);

        $container->addCompilerPass(new HWIOAuthPass());
    }

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        $app_id = $this->container->getParameter('redking_parse.app_id');
        $rest_key = $this->container->getParameter('redking_parse.rest_key');
        $master_key = $this->container->getParameter('redking_parse.master_key');

        ParseClient::initialize($app_id, $rest_key, $master_key);
    }
}
