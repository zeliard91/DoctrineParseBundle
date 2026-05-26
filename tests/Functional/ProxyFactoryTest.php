<?php

namespace Redking\ParseBundle\Tests\Functional;

use Doctrine\Persistence\Proxy;
use Redking\ParseBundle\Configuration;
use Redking\ParseBundle\Proxy\ProxyFactory;
use Redking\ParseBundle\Tests\Models\Blog\ChainNode;

/**
 * Coverage for ProxyFactory: identifier-based lazy ghost creation (with the id
 * eagerly readable without initialization), the injected Doctrine\Persistence
 * \Proxy marker, and the on-disk proxy-class generation paths that the default
 * AUTOGENERATE_EVAL test setup never exercises.
 */
class ProxyFactoryTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [];

    /** @var string[] */
    private array $tmpDirs = [];

    private function tmpDir(): string
    {
        $dir = sys_get_temp_dir().'/redking_proxytest_'.uniqid('', true);
        mkdir($dir, 0775, true);
        $this->tmpDirs[] = $dir;

        return $dir;
    }

    public function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            foreach (glob($dir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
        $this->tmpDirs = [];

        parent::tearDown();
    }

    public function testGetProxyReturnsUninitializedProxyWithReadableId(): void
    {
        $proxy = $this->om->getProxyFactory()->getProxy(ChainNode::class, ['id' => 'theId']);

        $this->assertInstanceOf(ChainNode::class, $proxy);
        $this->assertTrue($this->om->isUninitializedObject($proxy));

        // The id is a skipped property: reading it must not trigger the initializer.
        $this->assertSame('theId', $proxy->getId());
        $this->assertTrue(
            $this->om->isUninitializedObject($proxy),
            'reading the eagerly-set id must not initialize the proxy'
        );
    }

    public function testGeneratedProxyImplementsDoctrineProxyMarker(): void
    {
        $proxy = $this->om->getProxyFactory()->getProxy(ChainNode::class, ['id' => 'x']);

        $this->assertInstanceOf(Proxy::class, $proxy);
        $this->assertFalse($proxy->__isInitialized());
    }

    public function testGenerateProxyClassesWritesFilesToDisk(): void
    {
        $dir = $this->tmpDir();
        $factory = new ProxyFactory(
            $this->om,
            $dir,
            'RedkingTestProxies'.uniqid(),
            Configuration::AUTOGENERATE_FILE_NOT_EXISTS,
            false
        );

        $count = $factory->generateProxyClasses([$this->om->getClassMetadata(ChainNode::class)]);

        $this->assertSame(1, $count);
        $this->assertNotEmpty(glob($dir.'/*.php'), 'a proxy file must have been written');
    }

    public function testGenerateProxyClassesReturnsZeroWhenNativeLazyGhostsAreEnabled(): void
    {
        $factory = new ProxyFactory(
            $this->om,
            $this->tmpDir(),
            'RedkingTestProxies'.uniqid(),
            Configuration::AUTOGENERATE_ALWAYS,
            true // native lazy ghosts: no codegen needed
        );

        $this->assertSame(0, $factory->generateProxyClasses([$this->om->getClassMetadata(ChainNode::class)]));
    }

    public function testGenerateProxyClassesReturnsZeroWithoutProxyDir(): void
    {
        $factory = new ProxyFactory(
            $this->om,
            null,
            'RedkingTestProxies'.uniqid(),
            Configuration::AUTOGENERATE_ALWAYS,
            false
        );

        $this->assertSame(0, $factory->generateProxyClasses([$this->om->getClassMetadata(ChainNode::class)]));
    }

    public function testFileBasedProxyIsGeneratedRequiredAndUsable(): void
    {
        $factory = new ProxyFactory(
            $this->om,
            $this->tmpDir(),
            'RedkingTestProxies'.uniqid(),
            Configuration::AUTOGENERATE_ALWAYS,
            false
        );

        $proxy = $factory->getProxy(ChainNode::class, ['id' => 'fileId']);

        $this->assertInstanceOf(ChainNode::class, $proxy);
        $this->assertInstanceOf(Proxy::class, $proxy);
        $this->assertTrue($this->om->isUninitializedObject($proxy));
        $this->assertSame('fileId', $proxy->getId());
    }
}
