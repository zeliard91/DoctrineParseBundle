<?php

declare(strict_types=1);

namespace Redking\ParseBundle\Tests\DependencyInjection;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\DependencyInjection\RedkingParseExtension;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class MetadataCacheDriverWiringTest extends TestCase
{
    private const CACHE_SERVICE_ID = 'doctrine.parse.default_metadata_cache';

    public function testDefaultsToArrayAdapter(): void
    {
        $container = $this->loadExtension([]);

        $definition = $container->getDefinition(self::CACHE_SERVICE_ID);
        $this->assertSame(ArrayAdapter::class, $definition->getClass());
    }

    public function testApcuAdapter(): void
    {
        $container = $this->loadExtension(['metadata_cache_driver' => ['type' => 'apcu']]);

        $definition = $container->getDefinition(self::CACHE_SERVICE_ID);
        $this->assertSame(ApcuAdapter::class, $definition->getClass());
    }

    public function testRedisAdapterCreatesRedisInstanceAndAdapter(): void
    {
        $container = $this->loadExtension([
            'metadata_cache_driver' => [
                'type' => 'redis',
                'host' => '127.0.0.1',
                'port' => 6380,
            ],
        ]);

        $cacheDef = $container->getDefinition(self::CACHE_SERVICE_ID);
        $this->assertSame(RedisAdapter::class, $cacheDef->getClass());

        $args = $cacheDef->getArguments();
        $this->assertInstanceOf(Reference::class, $args[0]);
        $this->assertSame('doctrine.parse.default_redis_instance', (string) $args[0]);

        $instanceDef = $container->getDefinition('doctrine.parse.default_redis_instance');
        $calls = $instanceDef->getMethodCalls();
        $this->assertSame('connect', $calls[0][0]);
        $this->assertSame('127.0.0.1', $calls[0][1][0]);
        $this->assertSame(6380, $calls[0][1][1]);
    }

    public function testMemcachedAdapterCreatesMemcachedInstanceAndAdapter(): void
    {
        $container = $this->loadExtension([
            'metadata_cache_driver' => [
                'type' => 'memcached',
                'host' => 'cache.example',
                'port' => 11212,
                'instance_class' => 'Memcached',
            ],
        ]);

        $cacheDef = $container->getDefinition(self::CACHE_SERVICE_ID);
        $this->assertSame(MemcachedAdapter::class, $cacheDef->getClass());

        $args = $cacheDef->getArguments();
        $this->assertSame('doctrine.parse.default_memcached_instance', (string) $args[0]);

        $instanceDef = $container->getDefinition('doctrine.parse.default_memcached_instance');
        $this->assertSame('Memcached', $instanceDef->getClass());

        $calls = $instanceDef->getMethodCalls();
        $this->assertSame('addServer', $calls[0][0]);
        $this->assertSame('cache.example', $calls[0][1][0]);
        $this->assertSame(11212, $calls[0][1][1]);
    }

    public function testRedisAdapterHonorsCustomClass(): void
    {
        $container = $this->loadExtension([
            'metadata_cache_driver' => [
                'type'  => 'redis',
                'class' => CustomRedisAdapterStub::class,
                'host'  => 'localhost',
                'port'  => 6379,
            ],
        ]);

        $definition = $container->getDefinition(self::CACHE_SERVICE_ID);
        $this->assertSame(CustomRedisAdapterStub::class, $definition->getClass());
    }

    public function testMemcachedAdapterHonorsCustomClass(): void
    {
        $container = $this->loadExtension([
            'metadata_cache_driver' => [
                'type'  => 'memcached',
                'class' => CustomMemcachedAdapterStub::class,
                'host'  => 'localhost',
                'port'  => 11211,
                'instance_class' => 'Memcached',
            ],
        ]);

        $definition = $container->getDefinition(self::CACHE_SERVICE_ID);
        $this->assertSame(CustomMemcachedAdapterStub::class, $definition->getClass());
    }

    public function testServiceTypeAliasesProvidedId(): void
    {
        $container = $this->loadExtension([
            'metadata_cache_driver' => [
                'type' => 'service',
                'id'   => 'app.my_cache_pool',
            ],
        ]);

        $alias = $container->getAlias(self::CACHE_SERVICE_ID);
        $this->assertSame('app.my_cache_pool', (string) $alias);
    }

    public function testConfigurationReceivesMetadataCacheReference(): void
    {
        $container = $this->loadExtension([]);

        $calls = $container->getDefinition('doctrine_parse.default_configuration')->getMethodCalls();
        $setMetadataCacheArg = null;
        foreach ($calls as $call) {
            if ($call[0] === 'setMetadataCache') {
                $setMetadataCacheArg = $call[1][0];
                break;
            }
        }

        $this->assertInstanceOf(Reference::class, $setMetadataCacheArg);
        $this->assertSame(self::CACHE_SERVICE_ID, (string) $setMetadataCacheArg);
    }

    public function testUnknownTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"xcache" is an unrecognized Doctrine cache driver.');

        $this->loadExtension(['metadata_cache_driver' => ['type' => 'xcache']]);
    }

    private function loadExtension(array $extraConfig): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', false);

        $extension = new RedkingParseExtension();
        $extension->load([
            array_merge([
                'app_id'     => 'a',
                'rest_key'   => 'b',
                'master_key' => 'c',
                'server_url' => 'http://localhost:1337',
            ], $extraConfig),
        ], $container);

        return $container;
    }
}

class CustomRedisAdapterStub extends RedisAdapter
{
}

class CustomMemcachedAdapterStub extends MemcachedAdapter
{
}
