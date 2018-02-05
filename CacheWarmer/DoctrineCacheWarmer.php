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

use Doctrine\Common\Cache\ApcCache;
use Doctrine\Common\Cache\XcacheCache;
use Redking\ParseBundle\ObjectManager;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Clear all doctrine related caches (metadata, query and results)
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class DoctrineCacheWarmer implements CacheWarmerInterface
{
    /**
     * @var \Redking\ParseBundle\ObjectManager
     */
    private $om;

    /**
     * Constructor.
     *
     * @param ObjectManager $om A ObjectManager instance
     */
    public function __construct(ObjectManager $om)
    {
        $this->om = $om;
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
     * {@inheritdoc}
     */
    public function warmUp($cacheDir)
    {
        // Clear metadata cache
        $cacheDriver = $this->om->getConfiguration()->getMetadataCacheImpl();

        if ($cacheDriver) {
            $cacheDriver->flushAll();
        }
    }
}
