<?php

namespace Redking\ParseBundle\Tests\Functional;

use Doctrine\Common\EventManager;
use Parse\ParseMemoryStorage;
use Redking\ParseBundle\Configuration;
use Redking\ParseBundle\ObjectManager;
use Redking\ParseBundle\Security\EncryptionService;
use Redking\ParseBundle\Tests\Models\Blog\Invoice;

/**
 * Asserts that toggling Configuration::setUseLazyGhostObject(true) routes the
 * ProxyFactory through ReflectionClass::newLazyGhost() on PHP 8.4+.
 */
class NativeLazyGhostTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        Invoice::class,
    ];

    public function setUp(): void
    {
        if (PHP_VERSION_ID < 80400) {
            $this->markTestSkipped('Native lazy ghost objects require PHP 8.4+.');
        }

        $this->om = $this->createNativeLazyObjectManager();
        $this->uow = $this->om->getUnitOfWork();
    }

    public function testGetReferenceProducesNativeLazyGhost(): void
    {
        $invoice = new Invoice();
        $invoice->setReference('INV-NATIVE-001');
        $this->om->persist($invoice);
        $this->om->flush();

        $id = $invoice->getId();
        $this->om->clear();

        $reference = $this->om->getReference(Invoice::class, $id);

        $this->assertInstanceOf(Invoice::class, $reference);

        $reflClass = new \ReflectionClass($reference);
        $this->assertTrue($reflClass->isUninitializedLazyObject($reference), 'getReference must return a native lazy ghost on PHP 8.4');
        $this->assertTrue($this->om->isUninitializedObject($reference));

        // Accessing the reference field initializes the ghost.
        $this->assertSame('INV-NATIVE-001', $reference->getReference());
        $this->assertFalse($this->om->isUninitializedObject($reference));
    }

    private function createNativeLazyObjectManager(): ObjectManager
    {
        $config = new Configuration();
        $config->setAutoGenerateProxyClasses(Configuration::AUTOGENERATE_EVAL);
        $config->setProxyDir(\sys_get_temp_dir().'/Proxies');
        $config->setProxyNamespace('ParseProxies');
        $config->setUseLazyGhostObject(true);
        $config->setConnectionParameters([
            'server_url' => getenv('DOCTRINE_PARSE_SERVER_URL'),
            'app_id' => getenv('DOCTRINE_PARSE_APP_ID'),
            'master_key' => getenv('DOCTRINE_PARSE_MASTER_KEY'),
            'rest_key' => getenv('DOCTRINE_PARSE_REST_KEY'),
            'mount_path' => getenv('DOCTRINE_PARSE_MOUNT_PATH'),
            'metadata_cache_driver' => ['type' => 'redis', 'host' => 'localhost'],
        ]);
        $config->setMetadataDriverImpl($this->createMetadataDriverImpl());

        $om = new ObjectManager($config, new EventManager(), new ParseMemoryStorage());

        $encryptionService = new EncryptionService(
            'test_encryption_key_32_bytes_for_tests!',
            'test_hmac_key_32_bytes_for_tests!!'
        );
        $om->initializeEncryptionType($encryptionService);

        return $om;
    }
}
