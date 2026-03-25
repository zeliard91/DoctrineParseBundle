<?php

namespace Redking\ParseBundle;

use InvalidArgumentException;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\Mapping\ClassMetadataFactory;
use Parse\ParseSchema;

class SchemaManager
{
    /**
     * @var ObjectManager
     */
    protected $om;

    /**
     * @var ClassMetadataFactory
     */
    protected $cmf;

    public function __construct(ObjectManager $om, ClassMetadataFactory $cmf)
    {
        $this->om = $om;
        $this->cmf = $cmf;
    }

    public function dropCollections(): void
    {
        foreach ($this->cmf->getAllMetadata() as $class) {
            assert($class instanceof ClassMetadata);
            if ($class->isMappedSuperclass) {
                continue;
            }

            $this->dropCollection($class->name);
        }
    }

    public function dropCollection(string $className): void
    {
        $class = $this->om->getClassMetadata($className);
        if ($class->isMappedSuperclass) {
            throw new InvalidArgumentException('Cannot delete mapped super class');
        }

        $schema = new ParseSchema($class->getCollection());
        $schema->purge();
    }
}