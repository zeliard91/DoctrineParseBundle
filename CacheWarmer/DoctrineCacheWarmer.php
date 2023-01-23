<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Redking\ParseBundle\CacheWarmer;

use Redking\ParseBundle\ObjectManager;
use Redking\ParseBundle\Registry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Clear all doctrine related caches (metadata, query and results)
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class DoctrineCacheWarmer implements CacheWarmerInterface
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
     * @return string[]
     */
    public function warmUp($cacheDir)
    {
        // Clear metadata cache
        $registry = $this->container->get('doctrine_parse');
        assert($registry instanceof Registry);

        foreach ($registry->getManagers() as $om) {
            /** @var ObjectManager $om */
            $cacheDriver = $om->getConfiguration()->getMetadataCache();

            if ($cacheDriver) {
                $result = $cacheDriver->clear();
            }
        }
    }
}
