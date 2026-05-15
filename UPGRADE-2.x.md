# UPGRADE FROM 2.x

## Drop of `doctrine/cache`

The abandoned `doctrine/cache` package is no longer a dependency of this
bundle. The metadata cache is now exclusively configured through PSR-6
(`Psr\Cache\CacheItemPoolInterface`) backed by `symfony/cache` adapters.

### Removed public API

- `Redking\ParseBundle\Configuration::setMetadataCacheImpl()` and
  `Redking\ParseBundle\Configuration::getMetadataCacheImpl()` have been
  removed. Use `setMetadataCache(CacheItemPoolInterface)` and
  `getMetadataCache(): ?CacheItemPoolInterface` instead.
- `Redking\ParseBundle\Mapping\ClassMetadataFactory::getCacheDriver()`
  has been removed. Use the inherited `getCache(): ?CacheItemPoolInterface`.
- The compiler pass
  `Redking\ParseBundle\DependencyInjection\Compiler\CacheCompatibilityPass`
  has been removed.

### Configuration changes (`redking_parse.document_managers.*.metadata_cache_driver`)

Supported values for `type` are now: `service`, `memcached`, `redis`,
`apcu`, `array`. Any other value (`apc`, `xcache`, `wincache`,
`zenddata`, `memcache`, ...) raises an `InvalidArgumentException` at
container compilation.

If `class` is provided for `redis` or `memcached`, it must reference a
PSR-6 adapter (e.g. `Symfony\Component\Cache\Adapter\RedisAdapter` or
`Symfony\Component\Cache\Adapter\MemcachedAdapter`), not a
`Doctrine\Common\Cache\*` class.

#### Before

```yaml
redking_parse:
    document_managers:
        default:
            metadata_cache_driver:
                type: memcached
                class: Doctrine\Common\Cache\MemcacheCache
                host: localhost
                port: 11211
                instance_class: Memcache
```

#### After

```yaml
redking_parse:
    document_managers:
        default:
            metadata_cache_driver:
                type: memcached
                host: localhost
                port: 11211
                instance_class: Memcached
```

### Removed container parameters

The following parameters declared in `services.yml` are no longer
defined:

- `doctrine.parse.cache.array.class`
- `doctrine.parse.cache.apc.class`
- `doctrine.parse.cache.memcache.class`
- `doctrine.parse.cache.memcache_host`
- `doctrine.parse.cache.memcache_port`
- `doctrine.parse.cache.memcache_instance.class`
- `doctrine.parse.cache.xcache.class`
