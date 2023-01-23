<?php

declare(strict_types=1);

namespace Redking\ParseBundle\CacheWarmer;

use Redking\ParseBundle\Configuration;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\ObjectManager;
use Redking\ParseBundle\Registry;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

use function array_filter;
use function assert;
use function dirname;
use function file_exists;
use function is_dir;
use function is_writable;
use function mkdir;
use function sprintf;

/**
 * The proxy generator cache warmer generates all document proxies.
 *
 * In the process of generating proxies the cache for all the metadata is primed also,
 * since this information is necessary to build the proxies in the first place.
 *
 * @internal since version 4.4
 *
 * @psalm-suppress ContainerDependency
 */
class ProxyCacheWarmer implements CacheWarmerInterface
{
    /** @var ContainerInterface */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * This cache warmer is not optional, without proxies fatal error occurs!
     *
     * @return false
     */
    public function isOptional()
    {
        return false;
    }

    /**
     * @param string $cacheDir
     *
     * @return string[]
     */
    public function warmUp($cacheDir)
    {
        // we need the directory no matter the proxy cache generation strategy.
        $proxyCacheDir = (string) $this->container->getParameter('doctrine_parse.proxy_dir');
        if (! file_exists($proxyCacheDir)) {
            if (@mkdir($proxyCacheDir, 0775, true) === false && ! is_dir($proxyCacheDir)) {
                throw new RuntimeException(sprintf('Unable to create the Doctrine Proxy directory (%s)', dirname($proxyCacheDir)));
            }
        } elseif (! is_writable($proxyCacheDir)) {
            throw new RuntimeException(sprintf('Doctrine Proxy directory (%s) is not writable for the current system user.', $proxyCacheDir));
        }

        if ($this->container->getParameter('doctrine_parse.auto_generate_proxy_classes') === Configuration::AUTOGENERATE_EVAL) {
            return [];
        }

        $registry = $this->container->get('doctrine_parse');
        assert($registry instanceof Registry);

        foreach ($registry->getManagers() as $om) {
            /** @var ObjectManager $om */
            $classes = $this->getClassesForProxyGeneration($om);
            $om->getProxyFactory()->generateProxyClasses($classes);
        }

        return [];
    }

    /** @return ClassMetadata[] */
    private function getClassesForProxyGeneration(ObjectManager $om)
    {
        return array_filter($om->getMetadataFactory()->getAllMetadata(), static function (ClassMetadata $metadata) {
            return ! $metadata->isEmbeddedDocument && ! $metadata->isMappedSuperclass;
        });
    }
}
