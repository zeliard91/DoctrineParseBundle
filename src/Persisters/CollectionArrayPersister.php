<?php

namespace Redking\ParseBundle\Persisters;

use Redking\ParseBundle\PersistentCollection;
use Parse\ParseObject;

class CollectionArrayPersister extends AbstractCollectionPersister
{

    /**
     * Return Parse data based on the collection
     *
     * @param  PersistentCollection $coll
     * @return array
     */
    public function getParseData(PersistentCollection $coll)
    {
        $mapping = $coll->getMapping();

        $callback = function($v) use ($mapping) {
            return $this->prepareReferenceParseObject($mapping, $v);
        };

        // array_values to reindex values
        return array_values($coll->map($callback)->toArray());
    }

    /**
     * Apply collection transposition to ParseObject.
     *
     * @param  ParseObject          $object
     * @param  PersistentCollection $coll
     * @param  mixed                $data
     */
    public function applyParseData(ParseObject $object, PersistentCollection $coll, $data = false)
    {
        if ($data === false) {
            $data = $this->getParseData($coll);
        }
        if (count($data) == 0) {
            $data = null;
        }
        
        $fieldName = $coll->getMapping()['name'];
        if (is_array($data)) {
            $object->setArray($fieldName, $data);
        } else {
            $object->set($fieldName, $data);
        }
    }

    /**
     * Implements collection array updates.
     *
     * @param  PersistentCollection $coll
     */
    public function doUpdate(PersistentCollection $coll)
    {
        $originalData = $this->uow->getOriginalObjectData($coll->getOwner());
        
        $fieldName = $coll->getMapping()['name'];

        $setData = $this->getParseData($coll);
        $this->uow->addToCollectionChangeSet($coll->getOwner(), $fieldName, [$originalData->get($fieldName), $setData]);

        $this->applyParseData($originalData, $coll, $setData);
    }

    /**
     * Implements collection array deletes.
     *
     * @param  PersistentCollection $coll
     */
    public function doDelete(PersistentCollection $coll)
    {
        $originalData = $this->uow->getOriginalObjectData($coll->getOwner());
        
        $fieldName = $coll->getMapping()['name'];

        $setData = null;

        $this->uow->addToCollectionChangeSet($coll->getOwner(), $fieldName, [$originalData->get($fieldName), $setData]);

        $originalData->set($fieldName, $setData);
    }
}
