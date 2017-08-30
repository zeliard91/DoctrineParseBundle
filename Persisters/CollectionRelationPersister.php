<?php

namespace Redking\ParseBundle\Persisters;

use Redking\ParseBundle\PersistentCollection;
use Parse\ParseObject;

class CollectionRelationPersister extends AbstractCollectionPersister
{

    /**
     * Return Parse data based on the collection
     *
     * @param  PersistentCollection $coll
     * @return array
     */
    public function getParseData(PersistentCollection $coll)
    {

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

    }

    /**
     * Implements collection relation updates.
     *
     * @param  PersistentCollection $coll
     */
    public function doUpdate(PersistentCollection $coll)
    {
        $originalData = $this->uow->getOriginalObjectData($coll->getOwner());

        $fieldName = $coll->getMapping()['name'];
        foreach ($coll->getInsertDiff() as $object) {
            $relatedObject = $this->uow->getOriginalObjectData($object);
            if (null === $relatedObject->getObjectId()) {
                $this->uow->scheduleExtraUpdate($coll, [null, $object]);
            } else {
                $originalData->getRelation($fieldName)->add($relatedObject);
                
            }
        }
        foreach ($coll->getDeleteDiff() as $object) {
            $originalData->getRelation($fieldName)->remove($this->uow->getOriginalObjectData($object));
        }

        $this->uow->addToCollectionChangeSet($coll->getOwner(), $fieldName, [$coll->getSnapshot(), $coll->toArray()]);
    }

    /**
     * Update called by extraUpdates.
     *
     * @param  PersistentCollection $coll
     */
    public function updateAndSave(PersistentCollection $coll) {
        $this->update($coll);

        $originalData = $this->uow->getOriginalObjectData($coll->getOwner());
        $originalData->save($this->om->isMasterRequest());
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
        foreach ($coll->toArray() as $object) {
            $originalData->getRelation($fieldName)->remove($this->uow->getOriginalObjectData($object));
        }

        $this->uow->addToCollectionChangeSet($coll->getOwner(), $fieldName, [$originalData->get($fieldName), []]);
    }
}
