<?php

namespace Redking\ParseBundle\Tests;

use Doctrine\Common\EventManager;
use Redking\ParseBundle\Configuration;
use Redking\ParseBundle\ObjectManager;

abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ObjectManager
     */
    protected $om;

    /**
     * @var ObjectManager
     */
    protected $uow;

    public function setUp()
    {
        $this->om = $this->createTestObjectManager();
        $this->uow = $this->om->getUnitOfWork();
    }
    
    public static function createTestObjectManager()
    {
        $config = new Configuration();
        $config->setAutoGenerateProxyClasses(true);
        $config->setProxyDir(\sys_get_temp_dir().'/Proxies');
        $config->setProxyNamespace('ParseProxies');
        $config->setConnectionParameters([
            'server_url' => DOCTRINE_PARSE_SERVER_URL,
            'app_id' => DOCTRINE_PARSE_APP_ID,
            'master_key' => DOCTRINE_PARSE_MASTER_KEY,
            'rest_key' => DOCTRINE_PARSE_REST_KEY,
            'mount_path' => DOCTRINE_PARSE_MOUNT_PATH,
            ]);

        $eventManager = new EventManager();

        $om = new ObjectManager($config, $eventManager);

        return $om;
    }
}