<?php

namespace Redking\ParseBundle\CacheWarmer;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Redking\ParseBundle\Tools\SchemaManipulator;

/**
 * Check if the schema is synchronized with the metadata
 */
class SchemaSyncerWarmer implements CacheWarmerInterface
{
    /**
     * @var \Redking\ParseBundle\Tools\SchemaManipulator
     */
    private $schemaManipulator;

    /**
     * Constructor.
     *
     * @param \Redking\ParseBundle\Tools\SchemaManipulator $schemaManipulator
     */
    public function __construct(SchemaManipulator $schemaManipulator)
    {
        $this->schemaManipulator = $schemaManipulator;
    }

    /**
     * @return true
     */
    public function isOptional()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function warmUp($cacheDir)
    {
        $this->schemaManipulator->sync();
    }
}
