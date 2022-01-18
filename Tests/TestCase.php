<?php

namespace Redking\ParseBundle\Tests;

use Doctrine\Common\EventManager;
use Parse\ParseObject;
use Parse\ParseQuery;
use Parse\ParseMemoryStorage;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Redking\ParseBundle\Configuration;
use Redking\ParseBundle\Mapping\Driver\AnnotationDriver;
use Redking\ParseBundle\ObjectManager;

abstract class TestCase extends BaseTestCase
{
    /**
     * @var ObjectManager
     */
    protected $om;

    /**
     * @var ObjectManager
     */
    protected $uow;

    /**
     * @var array
     */
    protected static $modelSets = [];

    public function setUp(): void
    {
        $this->om = $this->createTestObjectManager();
        $this->uow = $this->om->getUnitOfWork();
    }
    
    public function createTestObjectManager()
    {
        $config = new Configuration();
        $config->setAutoGenerateProxyClasses(true);
        $config->setProxyDir(\sys_get_temp_dir().'/Proxies');
        $config->setProxyNamespace('ParseProxies');
        $config->setConnectionParameters([
            'server_url' => getenv('DOCTRINE_PARSE_SERVER_URL'),
            'app_id' => getenv('DOCTRINE_PARSE_APP_ID'),
            'master_key' => getenv('DOCTRINE_PARSE_MASTER_KEY'),
            'rest_key' => getenv('DOCTRINE_PARSE_REST_KEY'),
            'mount_path' => getenv('DOCTRINE_PARSE_MOUNT_PATH'),
            'metadata_cache_driver' => ['type' => 'redis', 'host' => 'localhost'],
            ]);
        $config->setMetadataDriverImpl($this->createMetadataDriverImpl());

        $eventManager = new EventManager();
        $storage = new ParseMemoryStorage();
        $om = new ObjectManager($config, $eventManager, $storage);

        return $om;
    }

    protected function createMetadataDriverImpl()
    {
        return AnnotationDriver::create(__DIR__ . '/Models');
    }

    /**
     * Emptyu collections after tests.
     */
    public function tearDown(): void
    {
        foreach (static::$modelSets as $className) {
            $collection = $this->om->getClassMetadata($className)->getCollection();

            $query = new ParseQuery($collection);
            $query->each(
                function (ParseObject $obj) {
                    $obj->destroy(true);
                },
                true
            );
        }
    }
}
