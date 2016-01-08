<?php

namespace Redking\ParseBundle\Persisters;

use Redking\ParseBundle\PersistentCollection;

class CollectionArrayPersister extends AbstractCollectionPersister
{

    /**
     * Implements collection array updates.
     *
     * @param  PersistentCollection $coll
     */
    public function doUpdate(PersistentCollection $coll)
    {
        $originalData = $this->uow->getOriginalObjectData($coll->getOwner());
        
        $mapping = $coll->getMapping();

        $callback = function($v) use ($mapping) {
            return $this->prepareReferenceParseObject($mapping, $v);
        };

        // array_values to reindex values
        $setData = array_values($coll->map($callback)->toArray());
        $this->uow->addToCollectionChangeSet($coll->getOwner(), $mapping['name'], [$originalData->get($mapping['name']), $setData]);

        $originalData->setArray($mapping['name'], $setData);
    }

    /**
     * Implements collection array deletes.
     *
     * @param  PersistentCollection $coll
     */
    public function doDelete(PersistentCollection $coll)
    {
        $originalData = $this->uow->getOriginalObjectData($coll->getOwner());
        
        $mapping = $coll->getMapping();

        $setData = null;

        $this->uow->addToCollectionChangeSet($coll->getOwner(), $mapping['name'], [$originalData->get($mapping['name']), $setData]);

        $originalData->set($mapping['name'], $setData);
    }
}
