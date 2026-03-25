<?php

namespace Redking\ParseBundle\Persisters;

use Redking\ParseBundle\ObjectManager;
use Redking\ParseBundle\PersistentCollection;
use Parse\ParseObject;

abstract class AbstractCollectionPersister
{
    /**
     * @var ObjectManager
     */
    protected $om;

    /**
     * @var \Redking\ParseBundle\UnitOfWork
     */
    protected $uow;

    /**
     * Initializes a new instance of a class derived from AbstractCollectionPersister.
     *
     * @param \Doctrine\ORM\EntityManager $em
     */
    public function __construct(ObjectManager $om)
    {
        $this->om = $om;
        $this->uow = $om->getUnitOfWork();
    }

    /**
     * Apply collection updates to the ParseObject.
     *
     * @param  PersistentCollection $coll
     */
    public function update(PersistentCollection $coll)
    {
        if (!$this->uow->isScheduledForInsert($coll->getOwner()) && !$this->uow->isScheduledForUpdate($coll->getOwner())) {
            $this->uow->scheduleForUpdate($coll->getOwner());
        }

        $this->doUpdate($coll);
    }

    abstract protected function doUpdate(PersistentCollection $coll);
    
    /**
     * Apply collection delete to the ParseObject.
     *
     * @param  PersistentCollection $coll
     */
    public function delete(PersistentCollection $coll)
    {
        if (!$this->uow->isScheduledForUpdate($coll->getOwner())) {
            $this->uow->scheduleForUpdate($coll->getOwner());
        }
        $this->doDelete($coll);
    }

    abstract protected function doDelete(PersistentCollection $coll);

    /**
     * Returns ParseObject based on Object value
     * @return \Parse\ParseObject
     */
    protected function prepareReferenceParseObject(array $mapping, $object)
    {
        return $this->uow->getOriginalObjectData($object);
    }

    abstract protected function getParseData(PersistentCollection $coll);

    /**
     * Called from computeChange to build a new ParseObject.
     * 
     * @param  ParseObject          $object
     * @param  PersistentCollection $coll
     * @param  mixed                $data   will be used if !== false
     */
    abstract protected function applyParseData(ParseObject $object, PersistentCollection $coll, $data = false);
}
