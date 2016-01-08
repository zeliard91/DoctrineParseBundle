<?php

namespace Redking\ParseBundle\Persisters;

use Redking\ParseBundle\ObjectManager;
use Redking\ParseBundle\PersistentCollection;

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
        $this->checkOwnerInScheduleUpdate($coll);
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
        $this->checkOwnerInScheduleUpdate($coll);
        $this->doDelete($coll);
    }

    abstract protected function doDelete(PersistentCollection $coll);

    
    /**
     * Check if collection's owner is schedule for update, if not, do it.
     *
     * @param  PersistentCollection $coll
     */
    protected function checkOwnerInScheduleUpdate(PersistentCollection $coll)
    {
        if (!$this->uow->isScheduledForUpdate($coll->getOwner())) {
            $this->scheduleForUpdate($coll->getOwner());
        }

    }

    /**
     * Returns ParseObject based on Object value
     * @return \Parse\ParseObject
     */
    protected function prepareReferenceParseObject(array $mapping, $object)
    {
        return $this->uow->getOriginalObjectData($object);
    }
}
