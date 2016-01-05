<?php

namespace Redking\ParseBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Parse\ParseClient;

class RedkingParseBundle extends Bundle
{
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
