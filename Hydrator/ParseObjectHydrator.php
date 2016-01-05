<?php

namespace Redking\ParseBundle\Hydrator;

use Redking\ParseBundle\ObjectManager;
use Redking\ParseBundle\Mapping\ClassMetadata;

class ParseObjectHydrator
{
    /**
     * @var ObjectManager
     */
    private $om;

    /**
     * @var ClassMetadata
     */
    private $class;

    public function __construct(ObjectManager $om, ClassMetadata $class)
    {
        $this->om = $om;
        $this->class = $class;
    }

    /**
     * Hydrate object.
     *
     * @param object             $object
     * @param \Parse\ParseObject $data   [description]
     * @param array              $hints  [description]
     *
     * @return [type] [description]
     */
    public function hydrate($object, \Parse\ParseObject $data, array $hints)
    {
        $this->class->reflFields['id']->setValue($object, $data->getObjectId());
        $this->class->reflFields['createdAt']->setValue($object, $data->getCreatedAt());
        $this->class->reflFields['updatedAt']->setValue($object, $data->getUpdatedAt());
        foreach ($this->class->fieldMappings as $key => $mapping) {
            if ($data->has($mapping['name']) && !isset($mapping['reference'])) {
                $this->class->reflFields[$key]->setValue($object, $data->get($mapping['name']));
            } // Load reference object
            elseif (isset($mapping['reference']) && $mapping['reference'] == true) {
                $reference_parse = $data->get($mapping['name']);
                if (is_object($reference_parse)) {
                    if ($reference_parse->isDataAvailable()) {
                        $reference = $this->om->getUnitOfWork()->getOrCreateObject($mapping['targetDocument'], $reference_parse, $hints);
                    } else {
                        $reference = $this->om->getReference($mapping['targetDocument'], $reference_parse->getObjectId(), $data->get($mapping['name']));
                    }
                    $this->class->reflFields[$key]->setValue($object, $reference);
                }
            }
        }
    }
}
