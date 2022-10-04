<?php

namespace Redking\ParseBundle;

use Exception;
use UnexpectedValueException;
use DeepCopy\DeepCopy;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\PropertyChangedListener;
use Redking\ParseBundle\Event\PreUpdateEventArgs;
use Redking\ParseBundle\Event\LifecycleEventArgs;
use Redking\ParseBundle\Event\ListenersInvoker;
use Redking\ParseBundle\Event\OnFlushEventArgs;
use Redking\ParseBundle\Event\PostFlushEventArgs;
use Redking\ParseBundle\Exception\RedkingParseException;
use Redking\ParseBundle\Hydrator\ParseObjectHydrator;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\Persisters\ObjectPersister;
use Redking\ParseBundle\Proxy\Proxy;
use Redking\ParseBundle\Types\Type;
use Parse\ParseACL;
use Parse\ParseFile;
use Parse\ParseGeoPoint;
use Parse\ParseObject;
use Parse\ParseRole;
use Parse\ParseUser;
use Redking\ParseBundle\Event\PreFlushEventArgs;
use Symfony\Component\HttpFoundation\File\File;

/**
 * The UnitOfWork is responsible for tracking changes to objects during an
 * "object-level" transaction and for writing out changes to the database
 * in the correct order.
 *
 * Internal note: This class contains highly performance-sensitive code.
 *
 * @since       2.0
 *
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 * @author      Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author      Jonathan Wage <jonwage@gmail.com>
 * @author      Roman Borschel <roman@code-factory.org>
 * @author      Rob Caiger <rob@clocal.co.uk>
 */
class UnitOfWork implements PropertyChangedListener
{
    /**
     * An entity is in MANAGED state when its persistence is managed by an EntityManager.
     */
    const STATE_MANAGED = 1;

    /**
     * An entity is new if it has just been instantiated (i.e. using the "new" operator)
     * and is not (yet) managed by an EntityManager.
     */
    const STATE_NEW = 2;

    /**
     * A detached entity is an instance with persistent state and identity that is not
     * (or no longer) associated with an EntityManager (and a UnitOfWork).
     */
    const STATE_DETACHED = 3;

    /**
     * A removed entity instance is an instance with a persistent identity,
     * associated with an EntityManager, whose persistent state will be deleted
     * on commit.
     */
    const STATE_REMOVED = 4;

    /**
     * The identity map holds references to all managed objects.
     *
     * Documents are grouped by their class name, and then indexed by the
     * serialized string of their database identifier field or, if the class
     * has no identifier, the SPL object hash. Serializing the identifier allows
     * differentiation of values that may be equal (via type juggling) but not
     * identical.
     *
     * Since all classes in a hierarchy must share the same identifier set,
     * we always take the root class name of the hierarchy.
     *
     * @var array
     */
    private $identityMap = array();

    /**
     * Map of all identifiers of managed objects.
     * Keys are object ids (spl_object_hash).
     *
     * @var array
     */
    private $objectIdentifiers = array();

    /**
     * Map of the original object data of managed objects.
     * Keys are object ids (spl_object_hash). This is used for calculating changesets
     * at commit time.
     *
     * @var array
     *
     * @internal Note that PHPs "copy-on-write" behavior helps a lot with memory usage.
     *           A value will only really be copied if the value in the object is modified
     *           by the user.
     */
    private $originalObjectData = array();

    /**
     * Map of object changes. Keys are object ids (spl_object_hash).
     * Filled at the beginning of a commit of the UnitOfWork and cleaned at the end.
     *
     * @var array
     */
    private $objectChangeSets = array();

    /**
     * Map of collection changes. Keys are object ids (spl_object_hash).
     * Filled in a commit by the collection persisters
     *
     * @var array
     */
    private $collectionChangeSets = array();

    /**
     * The (cached) states of any known objects.
     * Keys are object ids (spl_object_hash).
     *
     * @var array
     */
    private $objectStates = array();

    /**
     * The object persister instances used to persist entity instances.
     *
     * @var array
     */
    private $persisters = array();

    /**
     * The collection persister instances used to persist collections.
     *
     * @var array
     */
    private $collectionPersisters = array();

    /**
     * Map of objects that are scheduled for dirty checking at commit time.
     *
     * Documents are grouped by their class name, and then indexed by their SPL
     * object hash. This is only used for objects with a change tracking
     * policy of DEFERRED_EXPLICIT.
     *
     * @var array
     *
     * @todo rename: scheduledForSynchronization
     */
    private $scheduledForDirtyCheck = array();

    /**
     * A list of all pending object insertions.
     *
     * @var array
     */
    private $objectInsertions = array();

    /**
     * A list of all pending object updates.
     *
     * @var array
     */
    private $objectUpdates = array();

    /**
     * A list of all pending object upserts.
     *
     * @var array
     */
    private $objectUpserts = array();

    /**
     * Any pending extra updates that have been scheduled by persisters or events.
     *
     * @var array
     */
    private $extraUpdates = array();

    /**
     * A list of all pending object deletions.
     *
     * @var array
     */
    private $objectDeletions = array();

    /**
     * All pending collection deletions.
     *
     * @var array
     */
    private $collectionDeletions = array();

    /**
     * All pending collection updates.
     *
     * @var array
     */
    private $collectionUpdates = array();

    /**
     * List of collections visited during changeset calculation on a commit-phase of a UnitOfWork.
     * At the end of the UnitOfWork all these collections will make new snapshots
     * of their data.
     *
     * @var array
     */
    private $visitedCollections = array();

    /**
     * A list of objects related to collections scheduled for update or deletion.
     *
     * @var array
     */
    private $hasScheduledCollections = array();

    /**
     * The ObjectManager that "owns" this UnitOfWork instance.
     *
     * @var ObjectManager
     */
    private $om;

    /**
     * The EventManager used for dispatching events.
     *
     * @var EventManager
     */
    private $evm;

    /**
     * Additional documents that are scheduled for removal.
     *
     * @var array
     */
    private $orphanRemovals = array();

    /**
     * The ListenersInvoker used for dispatching events.
     *
     * @var \Redking\ParsBundle\Event\ListenersInvoker
     */
    private $listenersInvoker;

    /**
     * ParseObject cloner.
     *
     * @var \DeepCopy\DeepCopy
     */
    private $cloner;

    public function __construct(ObjectManager $om)
    {
        $this->om = $om;
        $this->evm = $om->getEventManager();
        $this->listenersInvoker = new ListenersInvoker($this->om);
        $this->cloner = new DeepCopy();
    }

    /**
     * @return \Redking\ParsBundle\Event\ListenersInvoker
     */
    public function getListenersInvoker()
    {
        return $this->listenersInvoker;
    }

    public function propertyChanged($object, $propertyName, $oldValue, $newValue): void
    {
        $oid = spl_object_hash($object);
        $class = $this->om->getClassMetadata(get_class($object));

        $isAssocField = isset($class->associationMappings[$propertyName]);

        if (!$isAssocField && !isset($class->fieldMappings[$propertyName])) {
            return; // ignore non-persistent fields
        }

        // Update changeset and mark object for synchronization
        $this->objectChangeSets[$oid][$propertyName] = array($oldValue, $newValue);

        if (!isset($this->scheduledForSynchronization[$class->name][$oid])) {
            $this->scheduleForDirtyCheck($object);
        }
    }

    /**
     * Tries to find an entity with the given identifier in the identity map of
     * this UnitOfWork.
     *
     * @param mixed  $id            The entity identifier to look for.
     * @param string $rootClassName The name of the root class of the mapped entity hierarchy.
     *
     * @return object|bool Returns the entity with the specified identifier if it exists in
     *                     this UnitOfWork, FALSE otherwise.
     */
    public function tryGetById($id, $rootClassName)
    {
        $idHash = implode(' ', (array) $id);

        if (isset($this->identityMap[$rootClassName][$idHash])) {
            return $this->identityMap[$rootClassName][$idHash];
        }

        return false;
    }

    /**
     * Checks whether an object is registered in the identity map of this UnitOfWork.
     *
     * @param object $object
     *
     * @return bool
     */
    public function isInIdentityMap($object)
    {
        $oid = spl_object_hash($object);

        if (!isset($this->objectIdentifiers[$oid])) {
            return false;
        }

        $classMetadata = $this->om->getClassMetadata(get_class($object));
        $idHash = implode(' ', [$this->objectIdentifiers[$oid]]);

        if ($idHash === '') {
            return false;
        }

        return isset($this->identityMap[$classMetadata->rootEntityName][$idHash]);
    }

    /**
     * Gets the identity map of the UnitOfWork.
     *
     * @return array
     */
    public function getIdentityMap()
    {
        return $this->objectIdentifiers;
    }

    /**
     * Returns the matching object from identityMap.
     *
     * @param  ParseObject $parseObject
     * @return object|null
     */
    public function getManagedObjectFromParseObject(ParseObject $parseObject)
    {
        foreach ($this->identityMap as $className => $objects) {
            if ($this->om->getClassMetadata($className)->getCollection() !== $parseObject->getClassName()) {
                continue;
            }
            foreach ($objects as $id => $object) {
                if ($id === $parseObject->getObjectId()) {
                    return $object;
                }
            }
        }

        return null;
    }

    /**
     * INTERNAL:
     * Removes an object from the identity map. This effectively detaches the
     * object from the persistence management of Doctrine.
     *
     * @ignore
     *
     * @param object $object
     *
     * @return bool
     *
     * @throws RedkingParseException
     */
    public function removeFromIdentityMap($object)
    {
        $oid = spl_object_hash($object);
        $classMetadata = $this->om->getClassMetadata(get_class($object));
        $idHash = implode(' ', [$this->objectIdentifiers[$oid]]);

        if ($idHash === '') {
            throw RedkingParseException::objectHasNoIdentity($object, 'remove from identity map');
        }

        $className = $classMetadata->rootEntityName;

        if (isset($this->identityMap[$className][$idHash])) {
            unset($this->identityMap[$className][$idHash]);
            unset($this->readOnlyObjects[$oid]);

            //$this->entityStates[$oid] = self::STATE_DETACHED;

            return true;
        }

        return false;
    }

    /**
     * Gets the EntityPersister for an Object.
     *
     * @param string $objectName The name of the object.
     *
     * @return \Redking\ParseBundle\ObjectPersister
     */
    public function getObjectPersister($objectName)
    {
        if (isset($this->persisters[$objectName])) {
            return $this->persisters[$objectName];
        }

        $class = $this->om->getClassMetadata($objectName);

        $persister = new ObjectPersister($this->om, $class);

        $this->persisters[$objectName] = $persister;

        return $this->persisters[$objectName];
    }

    /**
     * Returns Collection persister for an association
     * 
     * @param array $association
     * 
     * @return CollectionArrayPersister|CollectionRelationPersister
     */
    public function getCollectionPersister(array $association)
    {
        $implementation = $association['implementation'];

        if (isset($this->collectionPersisters[$implementation])) {
            return $this->collectionPersisters[$implementation];
        }

        switch ($implementation) {
            case ClassMetadata::ASSOCIATION_IMPL_ARRAY:
                $persister = new Persisters\CollectionArrayPersister($this->om);
                break;

            case ClassMetadata::ASSOCIATION_IMPL_RELATION:
                $persister = new Persisters\CollectionRelationPersister($this->om);
                break;
        }

        $this->collectionPersisters[$implementation] = $persister;

        return $this->collectionPersisters[$implementation];
    }

    
    /**
     * Add change to collection changeset.
     *
     * @param object $object    The object owner of the collection
     * @param string $field     The original field
     * @param array $changeSet  The changeset ([$oldValue, $newValue])
     */
    public function addToCollectionChangeSet($object, $field, array $changeSet)
    {
        $oid = spl_object_hash($object);

        if (!isset($this->collectionChangeSets[$oid])) {
            $this->collectionChangeSets[$oid] = [];
        }
        $this->collectionChangeSets[$oid][$field] = $changeSet;
    }

    /**
     * Returns collection changeSet.
     *
     * @param  string $oid
     * @return array
     */
    public function getCollectionChangeSet($oid)
    {
        return (isset($this->collectionChangeSets[$oid])) ? $this->collectionChangeSets[$oid] : [];
    }

    /**
     * INTERNAL:
     * Creates an object. Used for reconstitution of persistent object.
     *
     * Internal note: Highly performance-sensitive method.
     *
     * @ignore
     *
     * @param string $className The name of the object class.
     * @param object $data      The data for the object.
     * @param array  $hints     Any hints to account for during reconstitution/lookup of the object
     *
     * @return object The managed object instance.
     */
    public function getOrCreateObject($className, $data, &$hints = array(), $object = null)
    {
        $class = $this->om->getClassMetadata($className);
        $id = $data->getObjectId();
        $idHash = implode(' ', [$id]);
        $hydrator = new ParseObjectHydrator($this->om, $class);

        if (isset($this->identityMap[$class->rootEntityName][$idHash])) {
            if (isset($hints['doctrine.do_not_manage'])) {
                return $this->identityMap[$class->rootEntityName][$idHash];
            }
            $object = $this->identityMap[$class->rootEntityName][$idHash];
            $oid = spl_object_hash($object);

            if ($object instanceof Proxy && !$object->__isInitialized()) {
                $object->__setInitialized(true);
                $overrideLocalValues = true;
            } else {
                $overrideLocalValues = isset($hints['doctrine.refresh']);
            }
            if ($overrideLocalValues) {
                $this->originalObjectData[$oid] = $data;
                $this->clearObjectChangeSet($oid);
            }

            $hydrator->hydrate($object, $data, $hints);
        } else {
            if ($object == null) {
                $object = $class->newInstance();
            }

            $this->registerManaged($object, $id, $data);
            $hydrator->hydrate($object, $data, $hints);

            if (isset($hints['doctrine.do_not_manage'])) {
                $this->detach($object);
                return $object;
            }

            $this->identityMap[$class->rootEntityName][$idHash] = $object;
        }

        return $object;
    }

    /**
     * INTERNAL:
     * Registers a object as managed.
     *
     * TODO: This method assumes that $id is a valid PHP identifier for the
     * document class. If the class expects its database identifier to be a
     * MongoId, and an incompatible $id is registered (e.g. an integer), the
     * document identifiers map will become inconsistent with the identity map.
     * In the future, we may want to round-trip $id through a PHP and database
     * conversion and throw an exception if it's inconsistent.
     *
     * @param object $object The object.
     * @param array  $id     The identifier values.
     * @param array  $data   The original object data.
     */
    public function registerManaged($object, $id, ParseObject $data = null)
    {
        $oid = spl_object_hash($object);
        $class = $this->om->getClassMetadata(get_class($object));
        if (!$class->identifier || $id === null) {
            $this->objectIdentifiers[$oid] = $oid;
        } else {
            $this->objectIdentifiers[$oid] = $id;
        }

        $this->objectStates[$oid] = self::STATE_MANAGED;
        $this->originalObjectData[$oid] = $data;
        $this->addToIdentityMap($object);
    }

    /**
     * INTERNAL:
     * Registers an object in the identity map.
     * Note that entities in a hierarchy are registered with the class name of
     * the root object.
     *
     * @ignore
     *
     * @param object $object The object to register.
     *
     * @return bool TRUE if the registration was successful, FALSE if the identity of
     *              the object in question is already managed.
     *
     * @throws RedkingParseException
     */
    public function addToIdentityMap($object)
    {
        $classMetadata = $this->om->getClassMetadata(get_class($object));
        $idHash = implode(' ', [$this->objectIdentifiers[spl_object_hash($object)]]);

        if ($idHash === '') {
            throw RedkingParseException::objectWithoutIdentity($classMetadata->name, $object);
        }

        $className = $classMetadata->rootEntityName;

        if (isset($this->identityMap[$className][$idHash])) {
            return false;
        }

        $this->identityMap[$className][$idHash] = $object;

        return true;
    }

    /**
     * Gets the state of a object with regard to the current unit of work.
     *
     * @param object   $object
     * @param int|null $assume   The state to assume if the state is not yet known (not MANAGED or REMOVED).
     *                           This parameter can be set to improve performance of object state detection
     *                           by potentially avoiding a database lookup if the distinction between NEW and DETACHED
     *                           is either known or does not matter for the caller of the method.
     *
     * @return int The object state.
     */
    public function getObjectState($object, $assume = null)
    {
        $oid = spl_object_hash($object);

        if (isset($this->objectStates[$oid])) {
            return $this->objectStates[$oid];
        }

        $class = $this->om->getClassMetadata(get_class($object));

        if ($assume !== null) {
            return $assume;
        }

        /* State can only be NEW or DETACHED, because MANAGED/REMOVED states are
         * known. Note that you cannot remember the NEW or DETACHED state in
         * _documentStates since the UoW does not hold references to such
         * objects and the object hash can be reused. More generally, because
         * the state may "change" between NEW/DETACHED without the UoW being
         * aware of it.
         */
        $id = $class->getIdentifierObject($object);

        if ($id === null) {
            return self::STATE_NEW;
        }

        // Last try before DB lookup: check the identity map.
        if ($this->tryGetById($id, $class->rootEntityName)) {
            return self::STATE_DETACHED;
        }

        // DB lookup
        if ($this->getObjectPersister($class->name)->exists($object)) {
            return self::STATE_DETACHED;
        }

        return self::STATE_NEW;
    }

    /**
     * Gets the ParseObject of an object.
     *
     * @param object $object
     * @param string|null $oid
     *
     * @return ParseObject|null
     */
    public function getOriginalObjectData($object, string $oid = null): ?ParseObject
    {
        if (null === $oid) {
            $oid = spl_object_hash($object);
        }

        if (isset($this->originalObjectData[$oid])) {
            return $this->originalObjectData[$oid];
        }

        return null;
    }

    /**
     * Gets the ParseObject of an object from his spl id.
     *
     * @param string $oid
     *
     * @return ParseObject|null
     */
    public function getOriginalObjectDataByOid(string $oid): ?ParseObject
    {
        return $this->originalObjectData[$oid] ?? null;
    }

    /**
     * Persists a object as part of the current unit of work.
     *
     * @param object $object The object to persist.
     *
     * @throws MongoDBException          If trying to persist MappedSuperclass.
     * @throws \InvalidArgumentException If there is something wrong with object's identifier.
     */
    public function persist($object)
    {
        $class = $this->om->getClassMetadata(get_class($object));
        if ($class->isMappedSuperclass) {
            throw new \Exception('Can not persist super class '.$class->name);
        }
        $visited = array();
        $this->doPersist($object, $visited);
    }

    /**
     * Saves a object as part of the current unit of work.
     * This method is internally called during save() cascades as it tracks
     * the already visited objects to prevent infinite recursions.
     *
     * NOTE: This method always considers objects that are not yet known to
     * this UnitOfWork as NEW.
     *
     * @param object $object  The object to persist.
     * @param array  $visited The already visited objects.
     *
     * @throws \InvalidArgumentException
     * @throws MongoDBException
     */
    private function doPersist($object, array &$visited)
    {
        $oid = spl_object_hash($object);
        if (isset($visited[$oid])) {
            return; // Prevent infinite recursion
        }

        $visited[$oid] = $object; // Mark visited

        $class = $this->om->getClassMetadata(get_class($object));

        $documentState = $this->getObjectState($object, self::STATE_NEW);
        switch ($documentState) {
            case self::STATE_MANAGED:
                // Nothing to do, except if policy is "deferred explicit"
                if ($class->isChangeTrackingDeferredExplicit()) {
                    $this->scheduleForDirtyCheck($object);
                }
                break;
            case self::STATE_NEW:
                $this->persistNew($class, $object);
                break;

            case self::STATE_REMOVED:
                // Document becomes managed again
                unset($this->objectDeletions[$oid]);

                $this->objectStates[$oid] = self::STATE_MANAGED;
                break;

            case self::STATE_DETACHED:
                throw new \InvalidArgumentException(
                    'Behavior of persist() for a detached object is not yet defined.'
                );
                break;

            default:
                throw RedkingParseException::invalidDocumentState($documentState);
        }

        $this->cascadePersist($object, $visited);
    }

    /**
     * @param \Doctrine\ORM\Mapping\ClassMetadata $class
     * @param object                              $object
     */
    private function persistNew($class, $object)
    {
        $oid = spl_object_hash($object);
        $invoke = $this->listenersInvoker->getSubscribedSystems($class, Events::prePersist);

        if ($invoke !== ListenersInvoker::INVOKE_NONE) {
            $this->listenersInvoker->invoke($class, Events::prePersist, $object, new LifecycleEventArgs($object, $this->om), $invoke);
        }

        $this->objectIdentifiers[$oid] = $oid;

        $this->objectStates[$oid] = self::STATE_MANAGED;

        $this->scheduleForInsert($object);
    }

    /**
     * Schedules a object for insertion into the database.
     * If the object already has an identifier, it will be added to the
     * identity map.
     *
     * @param object $object The object to schedule for insertion.
     *
     * @throws \InvalidArgumentException
     */
    public function scheduleForInsert($object)
    {
        $oid = spl_object_hash($object);

        if (isset($this->objectUpdates[$oid])) {
            throw new \InvalidArgumentException('Dirty object can not be scheduled for insertion.');
        }
        if (isset($this->objectDeletions[$oid])) {
            throw new \InvalidArgumentException('Removed object can not be scheduled for insertion.');
        }
        if (isset($this->objectInsertions[$oid])) {
            throw new \InvalidArgumentException('Object can not be scheduled for insertion twice.');
        }

        $this->objectInsertions[$oid] = $object;

        if (isset($this->objectIdentifiers[$oid])) {
            $this->addToIdentityMap($object);
        }
    }

    /**
     * Gets the currently scheduled object insertions in this UnitOfWork.
     *
     * @return array
     */
    public function getScheduleForInsert()
    {
        return $this->objectInsertions;
    }

    /**
     * Gets the currently scheduled object updates in this UnitOfWork.
     *
     * @return array
     */
    public function getScheduleForUpdate()
    {
        return $this->objectUpdates;
    }

    /**
     * Gets the currently scheduled object deletions in this UnitOfWork.
     *
     * @return array
     */
    public function getScheduleForDelete()
    {
        return $this->objectDeletions;
    }

    /**
     * Cascades the save operation to associated objects.
     *
     * @param object $object
     * @param array  $visited
     */
    private function cascadePersist($object, array &$visited)
    {
        $class = $this->om->getClassMetadata(get_class($object));

        $associationMappings = array_filter(
            $class->associationMappings,
            function ($assoc) {
                return $assoc['isCascadePersist'];
            }
        );

        foreach ($associationMappings as $assoc) {
            $relatedObjects = $class->reflFields[$assoc['fieldName']]->getValue($object);

            switch (true) {
                case $relatedObjects instanceof PersistentCollection:
                    // Unwrap so that foreach() does not initialize
                    $relatedObjects = $relatedObjects->unwrap();
                    // break; is commented intentionally!

                case $relatedObjects instanceof Collection:
                case is_array($relatedObjects):
                    foreach ($relatedObjects as $relatedEntity) {
                        $this->doPersist($relatedEntity, $visited);
                    }
                    break;

                case $relatedObjects !== null:
                    $this->doPersist($relatedObjects, $visited);
                    break;

                default:
                    // Do nothing
            }
        }
    }

    /**
     * Commits the UnitOfWork, executing all operations that have been postponed
     * up to this point. The state of all managed entities will be synchronized with
     * the database.
     *
     * The operations are executed in the following order:
     *
     * 1) All object insertions
     * 2) All object updates
     * 3) All collection deletions
     * 4) All collection updates
     * 5) All object deletions
     *
     * @param null|object|array $object
     *
     * @throws \Exception
     */
    public function commit($object = null)
    {
        // Raise preFlush
        if ($this->evm->hasListeners(Events::preFlush)) {
            $this->evm->dispatchEvent(Events::preFlush, new PreFlushEventArgs($this->om));
        }

        // Compute changes done since last commit.
        if ($object === null) {
            $this->computeChangeSets();
        } elseif (is_object($object)) {
            $this->computeSingleObjectChangeSet($object);
        } elseif (is_array($object)) {
            foreach ($object as $object) {
                $this->computeSingleObjectChangeSet($object);
            }
        }

        if (!($this->objectInsertions ||
                $this->objectDeletions ||
                $this->objectUpdates ||
                $this->collectionUpdates ||
                $this->collectionDeletions ||
                $this->orphanRemovals)) {
            $this->dispatchOnFlushEvent();
            $this->dispatchPostFlushEvent();

            return; // Nothing to do.
        }

        if ($this->orphanRemovals) {
            foreach ($this->orphanRemovals as $orphan) {
                $this->remove($orphan);
            }
        }

        $this->dispatchOnFlushEvent();

        // Apply collection changes to originalData and treats them as updates.
        
        // Collection deletions (deletions of complete collections)
        foreach ($this->collectionDeletions as $collectionToDelete) {
            $this->getCollectionPersister($collectionToDelete->getMapping())->delete($collectionToDelete);
        }
        // Collection updates (deleteRows, updateRows, insertRows)
        foreach ($this->collectionUpdates as $collectionToUpdate) {
            $this->getCollectionPersister($collectionToUpdate->getMapping())->update($collectionToUpdate);
        }

        foreach ($this->getClassesForCommitAction($this->objectInsertions) as $classAndObjects) {
            list($class, $objects) = $classAndObjects;
            $this->executeInserts($class, $objects);
        }

        foreach ($this->getClassesForCommitAction($this->objectUpdates) as $classAndObjects) {
            list($class, $objects) = $classAndObjects;
            $this->executeUpdates($class, $objects);
        }

        // Extra updates that were requested by persisters.
        if ($this->extraUpdates) {
            $this->executeExtraUpdates();
        }

        foreach ($this->getClassesForCommitAction($this->objectDeletions, true) as $classAndObjects) {
            list($class, $objects) = $classAndObjects;
            $this->executeDeletions($class, $objects);
        }

        $this->dispatchPostFlushEvent();

        // Clear up
        $this->objectInsertions =
        $this->objectUpdates =
        $this->objectDeletions =
        $this->extraUpdates =
        $this->objectChangeSets =
        $this->collectionChangeSets =
        $this->collectionUpdates =
        $this->collectionDeletions =
        $this->visitedCollections =
        $this->scheduledForDirtyCheck =
        $this->orphanRemovals = array();
    }

    /**
     * Groups a list of scheduled objects by their class.
     *
     * @param array $objects         Scheduled objects (e.g. $this->objectInsertions)
     * @param bool  $includeEmbedded
     *
     * @return array Tuples of ClassMetadata and a corresponding array of objects
     */
    private function getClassesForCommitAction($objects, $includeEmbedded = false)
    {
        if (empty($objects)) {
            return array();
        }
        $divided = array();
        foreach ($objects as $oid => $d) {
            $className = get_class($d);
            if (isset($divided[$className])) {
                $divided[$className][1][$oid] = $d;
                continue;
            }
            $class = $this->om->getClassMetadata($className);
            if (empty($divided[$class->name])) {
                $divided[$class->name] = array($class, array($oid => $d));
            } else {
                $divided[$class->name][1][$oid] = $d;
            }
        }

        return $divided;
    }

    /**
     * Executes all entity insertions for objects of the specified type.
     *
     * @param \Doctrine\ORM\Mapping\ClassMetadata $class
     */
    private function executeInserts($class)
    {
        $objects = array();
        $className = $class->name;
        $persister = $this->getObjectPersister($className);
        $invoke = $this->listenersInvoker->getSubscribedSystems($class, Events::postPersist);

        foreach ($this->objectInsertions as $oid => $object) {
            if ($this->om->getClassMetadata(get_class($object))->name !== $className) {
                continue;
            }

            $persister->addInsert($object);

            unset($this->objectInsertions[$oid]);

            if ($invoke !== ListenersInvoker::INVOKE_NONE) {
                $objects[] = $object;
            }
        }

        $postInsertIds = $persister->executeInserts();

        if ($postInsertIds) {
            // Persister returned post-insert IDs
            foreach ($postInsertIds as $id => $results) {
                $oid = spl_object_hash($results['object']);
                $idField = $class->identifier;

                $class->reflFields[$idField]->setValue($results['object'], $id);
                $class->reflFields['createdAt']->setValue($results['object'], $results['parseObject']->getCreatedAt());
                $class->reflFields['updatedAt']->setValue($results['object'], $results['parseObject']->getUpdatedAt());

                $this->objectIdentifiers[$oid] = $id;
                $this->objectStates[$oid] = self::STATE_MANAGED;
                $this->originalObjectData[$oid] = $results['parseObject'];

                if (isset($this->identityMap[$class->rootEntityName][$oid])) {
                    unset($this->identityMap[$class->rootEntityName][$oid]);
                }
                $this->addToIdentityMap($results['object']);
            }
        }

        foreach ($objects as $object) {
            $this->listenersInvoker->invoke($class, Events::postPersist, $object, new LifecycleEventArgs($object, $this->om), $invoke);
        }
    }

    /**
     * Executes all object updates for entities of the specified type.
     *
     * @param \Doctrine\ORM\Mapping\ClassMetadata $class
     */
    private function executeUpdates(ClassMetadata $class, array $objects)
    {
        $className = $class->name;
        $persister = $this->getObjectPersister($className);
        $preUpdateInvoke = $this->listenersInvoker->getSubscribedSystems($class, Events::preUpdate);
        $postUpdateInvoke = $this->listenersInvoker->getSubscribedSystems($class, Events::postUpdate);

        $updatedOids = [];
        foreach ($objects as $oid => $object) {
            if ($this->om->getClassMetadata(get_class($object))->name !== $className) {
                continue;
            }

            if (!isset($this->objectChangeSets[$oid]) && !empty($this->getCollectionChangeSet($oid))) {
                $this->objectChangeSets[$oid] = [];
            }

            if ($preUpdateInvoke != ListenersInvoker::INVOKE_NONE && isset($this->objectChangeSets[$oid])) {
                $this->listenersInvoker->invoke($class, Events::preUpdate, $object, new PreUpdateEventArgs($object, $this->om, $this->objectChangeSets[$oid]), $preUpdateInvoke);
                $this->recomputeSingleObjectChangeSet($class, $object);
            }

            if (!empty($this->objectChangeSets[$oid]) || !empty($this->getCollectionChangeSet($oid))) {
                $updatedOids[] = $oid;
                $persister->addUpdate($oid, $this->objectChangeSets[$oid]+$this->getCollectionChangeSet($oid));
            }
        }

        $updatedAts = $persister->executeUpdates();

        foreach ($objects as $oid => $object) {
            if (isset($updatedAts[$oid])) {
                $object->setUpdatedAt($updatedAts[$oid]);
            }
            unset($this->objectUpdates[$oid]);
            if (isset($this->collectionChangeSets[$oid])) {
                unset($this->collectionChangeSets[$oid]);
            }

            if ($postUpdateInvoke != ListenersInvoker::INVOKE_NONE) {
                $this->listenersInvoker->invoke($class, Events::postUpdate, $object, new LifecycleEventArgs($object, $this->om), $postUpdateInvoke);
            }
        }
    }

    /**
     * Executes any extra updates that have been scheduled.
     */
    private function executeExtraUpdates()
    {
        foreach ($this->extraUpdates as $oid => $update) {
            list ($object, $changeset) = $update;

            if ($object instanceof Collection) {
                $this->getCollectionPersister($object->getMapping())->updateAndSave($object);
            } else {
                $this->getObjectPersister(get_class($object))->update($this->originalObjectData[$oid], $changeset);
            }
        }
    }

    /**
     * Executes all object deletions for objects of the specified type.
     *
     * @param \Doctrine\ORM\Mapping\ClassMetadata $class
     */
    private function executeDeletions($class)
    {
        $className = $class->name;
        $persister = $this->getObjectPersister($className);
        $invoke = $this->listenersInvoker->getSubscribedSystems($class, Events::postRemove);
        $deletedOids = [];

        foreach ($this->objectDeletions as $oid => $object) {
            if ($this->om->getClassMetadata(get_class($object))->name !== $className || !isset($this->originalObjectData[$oid])) {
                continue;
            }

            $deletedOids[] = $oid;
            $persister->addDelete($oid);
        }

        $persister->executeDeletions();

        foreach ($this->objectDeletions as $oid => $object) {
            if (!in_array($oid, $deletedOids)) {
                continue;
            }
            unset(
                $this->objectDeletions[$oid],
                $this->objectIdentifiers[$oid],
                $this->originalObjectData[$oid],
                $this->objectStates[$oid]
            );

            // Object with this $oid after deletion treated as NEW, even if the $oid
            // is obtained by a new object because the old one went out of scope.
            $class->reflFields[$class->identifier]->setValue($object, null);

            if ($invoke !== ListenersInvoker::INVOKE_NONE) {
                $this->listenersInvoker->invoke($class, Events::postRemove, $object, new LifecycleEventArgs($object, $this->om), $invoke);
            }
        }
    }

    /**
     * Only flush the given object according to a ruleset that keeps the UoW consistent.
     *
     * 1. All objects scheduled for insertion and (orphan) removals are processed as well!
     * 2. Proxies are skipped.
     * 3. Only if object is properly managed.
     *
     * @param  object $object
     * @throws \InvalidArgumentException If the document is not STATE_MANAGED
     * @return void
     */
    private function computeSingleObjectChangeSet($object)
    {
        $state = $this->getObjectState($object);

        if ($state !== self::STATE_MANAGED && $state !== self::STATE_REMOVED) {
            throw new \InvalidArgumentException("Object has to be managed or scheduled for removal for single computation " . RedkingParseException::objToStr($object));
        }

        $class = $this->om->getClassMetadata(get_class($object));

        if ($state === self::STATE_MANAGED && $class->isChangeTrackingDeferredImplicit()) {
            $this->persist($object);
        }

        // Compute changes for INSERTed and UPSERTed object first. This must always happen even in this case.
        $this->computeScheduleInsertsChangeSets();

        // Ignore uninitialized proxy objects
        if ($object instanceof Proxy && ! $object->__isInitialized__) {
            return;
        }

        // Only MANAGED objects that are NOT SCHEDULED FOR INSERTION OR DELETION are processed here.
        $oid = spl_object_hash($object);

        if ( ! isset($this->objectInsertions[$oid])
            && ! isset($this->objectDeletions[$oid])
            && isset($this->objectStates[$oid])
        ) {
            $this->computeChangeSet($class, $object);
        }
    }

    /**
     * Computes all the changes that have been done to entities and collections
     * since the last commit and stores these changes in the _entityChangeSet map
     * temporarily for access by the persisters, until the UoW commit is finished.
     */
    public function computeChangeSets()
    {
        // Compute changes for INSERTed objects first. This must always happen.
        $this->computeScheduleInsertsChangeSets();

        // Compute changes for other MANAGED objects. Change tracking policies take effect here.
        foreach ($this->identityMap as $className => $objects) {
            $class = $this->om->getClassMetadata($className);

            // Skip class if instances are read-only
            if ($class->isReadOnly) {
                continue;
            }

            // If change tracking is explicit or happens through notification, then only compute
            // changes on objects of that type that are explicitly marked for synchronization.
            switch (true) {
                case $class->isChangeTrackingDeferredImplicit():
                    $objectTorProcess = $objects;
                    break;

                case isset($this->scheduledForDirtyCheck[$className]):
                    $objectTorProcess = $this->scheduledForDirtyCheck[$className];
                    break;

                default:
                    $objectTorProcess = array();

            }

            foreach ($objectTorProcess as $object) {
                // Ignore uninitialized proxy objects
                if ($object instanceof Proxy && !$object->__isInitialized__) {
                    continue;
                }

                // Only MANAGED objects that are NOT SCHEDULED FOR INSERTION are processed here.
                $oid = spl_object_hash($object);

                if (!isset($this->objectInsertions[$oid]) && !isset($this->objectDeletions[$oid]) && isset($this->objectStates[$oid])) {
                    $this->computeChangeSet($class, $object);
                }
            }
        }
    }

    /**
     * Gets the changeset for an object.
     *
     * @param object $object
     *
     * @return array
     */
    public function & getObjectChangeSet($object)
    {
        $oid  = spl_object_hash($object);
        $data = [];

        if (!isset($this->objectChangeSets[$oid])) {
            return $data;
        }

        return $this->objectChangeSets[$oid];
    }

    /**
     * Computes the changesets of all objects scheduled for insertion.
     */
    private function computeScheduleInsertsChangeSets()
    {
        foreach ($this->objectInsertions as $object) {
            $class = $this->om->getClassMetadata(get_class($object));

            $this->computeChangeSet($class, $object);
        }
    }

    /**
     * Computes the changes that happened to a single entity.
     *
     * Modifies/populates the following properties:
     *
     * {@link _originalObjectData}
     * If the entity is NEW or MANAGED but not yet fully persisted (only has an id)
     * then it was not fetched from the database and therefore we have no original
     * entity data yet. All of the current entity data is stored as the original entity data.
     *
     * {@link _objectChangeSets}
     * The changes detected on all properties of the entity are stored there.
     * A change is a tuple array where the first entry is the old value and the second
     * entry is the new value of the property. Changesets are used by persisters
     * to INSERT/UPDATE the persistent entity state.
     *
     * {@link _objectUpdates}
     * If the entity is already fully MANAGED (has been fetched from the database before)
     * and any changes to its properties are detected, then a reference to the entity is stored
     * there to mark it for an update.
     *
     * {@link _collectionDeletions}
     * If a PersistentCollection has been de-referenced in a fully MANAGED entity,
     * then this collection is marked for deletion.
     *
     * @ignore
     *
     * @internal Don't call from the outside.
     *
     * @param ClassMetadata $class  The class descriptor of the entity.
     * @param object        $object The entity for which to compute the changes.
     */
    public function computeChangeSet(ClassMetadata $class, $object)
    {
        $oid = spl_object_hash($object);

        if (isset($this->readOnlyObjects[$oid])) {
            return;
        }

        if (!$class->isInheritanceTypeNone()) {
            $class = $this->om->getClassMetadata(get_class($object));
        }

        $invoke = $this->listenersInvoker->getSubscribedSystems($class, Events::preFlush) & ~ListenersInvoker::INVOKE_MANAGER;

        if ($invoke !== ListenersInvoker::INVOKE_NONE) {
            $this->listenersInvoker->invoke($class, Events::preFlush, $object, new PreFlushEventArgs($this->om), $invoke);
        }

        if (isset($this->originalObjectData[$oid])) {
            $actualData = $this->cloner->copy($this->originalObjectData[$oid]);
        } else {
            $actualData = $this->getObjectPersister(get_class($object))->instanciateParseObject();
        }

        $this->applyChangesToParseObject($class, $actualData, $object);

        if (!isset($this->originalObjectData[$oid])) {
            // Entity is either NEW or MANAGED but not yet fully persisted (only has an id).
            // These result in an INSERT.
            $this->originalObjectData[$oid] = $actualData;
            $changeSet = array();

            foreach ($actualData as $propName => $actualValue) {
                if (!isset($class->associationMappings[$propName])) {
                    $changeSet[$propName] = array(null, $actualValue);

                    continue;
                }

                $assoc = $class->associationMappings[$propName];

                if ($assoc['isOwningSide'] && $assoc['type'] & ClassMetadata::ONE) {
                    $changeSet[$propName] = array(null, $actualValue);
                }
            }

            $this->objectChangeSets[$oid] = $changeSet;
        } else {
            // Entity is "fully" MANAGED: it was already fully persisted before
            // and we have a copy of the original data
            $originalData = $this->originalObjectData[$oid];
            $isChangeTrackingNotify = $class->isChangeTrackingNotify();
            $changeSet = ($isChangeTrackingNotify && isset($this->objectChangeSets[$oid]))
                ? $this->objectChangeSets[$oid]
                : array();

            $changeSet = $this->getChangesetFromParseObjects($class, $actualData, $originalData, $changeSet);

            if ($changeSet) {
                $this->objectChangeSets[$oid] = $changeSet;
                // apply changes on original data
                foreach ($changeSet as $key => $values) {
                    if ($key === '_ACL') {
                        $this->originalObjectData[$oid]->setAcl($values);
                        continue;
                    }
                    if ($class->isFieldAnArray($class->getFieldNameOfName($key))) {
                        if (is_array($values[1])) {
                            $this->originalObjectData[$oid]->setArray($key, $values[1]);
                        }
                        continue;
                    }
                    if ($class->isFieldAnHash($class->getFieldNameOfName($key)) || $class->isFieldAnObject($class->getFieldNameOfName($key))) {
                        if (is_array($values[1])) {
                            $this->originalObjectData[$oid]->setAssociativeArray($key, $values[1]);
                        }
                        continue;
                    }

                    $this->originalObjectData[$oid]->set($key, $values[1]);
                }
                $actualData = $this->originalObjectData[$oid];

                $this->objectUpdates[$oid] = $object;
            }
        }

        // Look for changes in associations of the entity
        foreach ($class->associationMappings as $field => $assoc) {
            if (($val = $class->reflFields[$field]->getValue($object)) !== null) {
                $this->computeAssociationChanges($assoc, $val);
                if (!isset($this->objectChangeSets[$oid]) &&
                    $assoc['isOwningSide'] &&
                    $assoc['type'] == ClassMetadata::MANY &&
                    $val instanceof PersistentCollection &&
                    $val->isDirty()) {
                    $this->objectChangeSets[$oid] = array();
                    $this->originalObjectData[$oid] = $actualData;
                    $this->objectUpdates[$oid] = $object;
                }
            }
        }
    }

    /**
     * Apply changes from object to actualData.
     *
     * @param  ClassMetadata $class      [description]
     * @param  ParseObject   $actualData [description]
     * @param  [type]        $object     [description]
     */
    protected function applyChangesToParseObject(ClassMetadata $class, ParseObject $actualData, $object)
    {
        foreach ($class->reflFields as $name => $refProp) {
            $value = $refProp->getValue($object);

            if ($class->isCollectionValuedAssociation($name) && $value !== null) {
                if ($value instanceof PersistentCollection) {
                    if ($value->getOwner() === $object) {
                        continue;
                    }

                    $value = new ArrayCollection($value->getValues());
                }

                // If $value is not a Collection then use an ArrayCollection.
                if (!$value instanceof Collection) {
                    $value = new ArrayCollection($value);
                }

                $assoc = $class->associationMappings[$name];

                // Inject PersistentCollection
                $value = new PersistentCollection(
                    $this->om,
                    $this->om->getClassMetadata($assoc['targetDocument']),
                    $value
                );
                $value->setOwner($object, $assoc);
                $value->setDirty(!$value->isEmpty());

                $class->reflFields[$name]->setValue($object, $value);

                // We set data only on owning side
                if ($class->isOwningCollectionValuedAssociation($name)) {
                    $this->getCollectionPersister($assoc)->applyParseData($actualData, $value);
                }

                continue;
            }

            // if single ref, try to get the one for uow
            if ($class->isSingleValuedAssociation($name) && $value !== null) {
                $ref_oid = spl_object_hash($value);
                $assoc = $class->associationMappings[$name];

                // skip if it is not the owning side
                if ( ! $assoc['isOwningSide']) {
                    continue;
                }
                // if not found, we transform the object in a ParseObject if there is a cascade persist
                if (!isset($this->originalObjectData[$ref_oid])) {
                    if ($assoc['isCascadePersist']) {
                        $this->originalObjectData[$ref_oid] = $this->getObjectPersister(get_class($value))->instanciateParseObject();
                    } else {
                        continue;
                    }
                }

                $actualData->set($class->getNameOfField($name), $this->originalObjectData[$ref_oid]);
                continue;
            }

            // skip if the field's value is null and the object is new
            if (null === $actualData->getObjectId() && null === $value) {
                continue;
            }

            // skip if the field is ParseUser password and value is null
            if (null === $value && $class->getNameOfField($name) === 'password' and $actualData instanceof ParseUser) {
                continue;
            }

            // skip if the field is the same ParseFile but it has not been loaded
            if ($value instanceof ParseFile 
                && $actualData->get($class->getNameOfField($name)) instanceof ParseFile
                && $value->getName() === $actualData->get($class->getNameOfField($name))->getName()
            ) {
                continue;
            }

            // Transform a Symfony File into a ParseFile
            if ($value instanceof File
                && $class->isFieldAFile($name)) {
                $actualData->set($class->getNameOfField($name), ParseFile::createFromFile($value->getRealPath(), $value->getBasename(), $value->getMimeType()));

                continue;
            }

            if ($class->isFieldAnArray($name)) {
                if (is_array($value)) {
                    $actualData->setArray($class->getNameOfField($name), $value);
                }
                continue;
            }

            if ($class->isFieldAnHash($name) || $class->isFieldAnObject($name)) {
                if (is_array($value)) {
                    $actualData->setAssociativeArray($class->getNameOfField($name), $value);
                }
                elseif (is_object($value)) {
                    $actualData->set($class->getNameOfField($name), $value);
                }
                continue;
            }

            if ($value instanceof ParseGeoPoint
                && $actualData->get($class->getNameOfField($name)) instanceof ParseGeoPoint
                && $value->_encode() === $actualData->get($class->getNameOfField($name))->_encode()
            ) {
                continue;
            }

            if ($value instanceof \BackedEnum) {
                $actualData->set($class->getNameOfField($name), $value->value);
                continue;
            }

            if (!$class->isIdentifier($name) && $name !== 'createdAt') {
                // Force string if needed
                if ($class->getTypeOfField($name) === Type::STRING && null !== $value) {
                    $actualData->set($class->getNameOfField($name), (string)$value);
                }
                // Force integer if needed
                elseif ($class->getTypeOfField($name) === Type::INTEGER && null !== $value) {
                    $actualData->set($class->getNameOfField($name), (int)$value);
                }
                // Force float if needed
                elseif ($class->getTypeOfField($name) === Type::FLOAT && null !== $value) {
                    $actualData->set($class->getNameOfField($name), (float)$value);
                }
                // Convert date to UTC
                elseif ($class->getTypeOfField($name) === Type::DATE && null !== $value) {
                    $date = clone $value;
                    $date->setTimezone(new \DateTimeZone('UTC'));
                    $actualData->set($class->getNameOfField($name), $date);
                }
                else {
                    $actualData->set($class->getNameOfField($name), $value);
                }
            }
        }

        $this->applyAcl($object, $actualData);
    }

    /**
     * Computes the changes of an association.
     *
     * @param array $assoc
     * @param mixed $value The value of the association.
     *
     * @throws RedkingParseException
     * @throws ORMException
     */
    private function computeAssociationChanges($assoc, $value)
    {
        if ($value instanceof Proxy && !$value->__isInitialized__) {
            return;
        }

        if ($value instanceof PersistentCollection && $value->isDirty()) {
            $coid = spl_object_hash($value);

            if ($assoc['isOwningSide']) {
                $this->collectionUpdates[$coid] = $value;
            }

            $this->visitedCollections[$coid] = $value;
        }

        // Look through the entities, and in any of their associations,
        // for transient (new) entities, recursively. ("Persistence by reachability")
        // Unwrap. Uninitialized collections will simply be empty.
        $unwrappedValue = ($assoc['type'] === ClassMetadata::ONE) ? array($value) : $value->unwrap();
        $targetClass = $this->om->getClassMetadata($assoc['targetDocument']);

        foreach ($unwrappedValue as $key => $entry) {
            $state = $this->getObjectState($entry, self::STATE_NEW);

            if (!($entry instanceof $assoc['targetDocument'])) {
                throw new \Exception(
                    sprintf(
                        'Found object of type %s on association %s, but expecting %s',
                        get_class($entry),
                        $assoc['fieldName'],
                        $targetClass->name
                    )
                );
            }

            switch ($state) {
                case self::STATE_NEW:
                    if (!$assoc['isCascadePersist']) {
                        throw RedkingParseException::newObjectFoundThroughRelationship($assoc, $entry);
                    }

                    $this->persistNew($targetClass, $entry);
                    $this->computeChangeSet($targetClass, $entry);
                    break;

                case self::STATE_REMOVED:
                    // Consume the $value as array (it's either an array or an ArrayAccess)
                    // and remove the element from Collection.
                    if ($assoc['type'] === ClassMetadata::MANY) {
                        unset($value[$key]);
                    }
                    break;

                case self::STATE_DETACHED:
                    // Can actually not happen right now as we assume STATE_NEW,
                    // so the exception will be raised from the DBAL layer (constraint violation).
                    throw RedkingParseException::detachedObjectFoundThroughRelationship($assoc, $entry);
                    break;

                default:
                    // MANAGED associated entities are already taken into account
                    // during changeset calculation anyway, since they are in the identity map.
            }
        }
    }

    private function dispatchOnFlushEvent()
    {
        if ($this->evm->hasListeners(Events::onFlush)) {
            $this->evm->dispatchEvent(Events::onFlush, new OnFlushEventArgs($this->om));
        }
    }

    private function dispatchPostFlushEvent()
    {
        if ($this->evm->hasListeners(Events::postFlush)) {
            $this->evm->dispatchEvent(Events::postFlush, new PostFlushEventArgs($this->om));
        }
    }

    /**
     * INTERNAL:
     * Schedules an embedded object for removal. The remove() operation will be
     * invoked on that object at the beginning of the next commit of this
     * UnitOfWork.
     *
     * @ignore
     *
     * @param object $object
     */
    public function scheduleOrphanRemoval($object)
    {
        // Search for doctrine managed object from Parse Object
        if ($object instanceof ParseObject) {
            $object = $this->getManagedObjectFromParseObject($object);
            if (null === $object) {
                throw RedkingParseException::objectNotManaged($object);
            }
        }
        $this->orphanRemovals[spl_object_hash($object)] = $object;
    }

    /**
     * INTERNAL:
     * Unschedules an embedded or referenced object for removal.
     *
     * @ignore
     *
     * @param object $object
     */
    public function unscheduleOrphanRemoval($object)
    {
        $oid = spl_object_hash($object);
        if (isset($this->orphanRemovals[$oid])) {
            unset($this->orphanRemovals[$oid]);
        }
    }

    /**
     * Deletes a object as part of the current unit of work.
     *
     * @param object $object The object to remove.
     */
    public function remove($object)
    {
        $visited = array();
        $this->doRemove($object, $visited);
    }

    /**
     * Deletes an object as part of the current unit of work.
     *
     * This method is internally called during delete() cascades as it tracks
     * the already visited entities to prevent infinite recursions.
     *
     * @param object $object  The object to delete.
     * @param array  $visited The map of the already visited entities.
     *
     * @throws RedkingParseException If the instance is a detached object.
     * @throws UnexpectedValueException
     */
    private function doRemove($object, array &$visited)
    {
        $oid = spl_object_hash($object);

        if (isset($visited[$oid])) {
            return; // Prevent infinite recursion
        }

        $visited[$oid] = $object; // mark visited

        // Cascade first, because scheduleForDelete() removes the object from the idobject map, which
        // can cause problems when a lazy proxy has to be initialized for the cascade operation.
        $this->cascadeRemove($object, $visited);

        $class = $this->om->getClassMetadata(get_class($object));
        $objectState = $this->getobjectState($object);

        switch ($objectState) {
            case self::STATE_NEW:
            case self::STATE_REMOVED:
                // nothing to do
                break;

            case self::STATE_MANAGED:
                $invoke = $this->listenersInvoker->getSubscribedSystems($class, Events::preRemove);

                if ($invoke !== ListenersInvoker::INVOKE_NONE) {
                    $this->listenersInvoker->invoke($class, Events::preRemove, $object, new LifecycleEventArgs($object, $this->om), $invoke);
                }

                $this->scheduleForDelete($object);
                break;

            case self::STATE_DETACHED:
                throw RedkingParseException::detachedObjectCannot($object, 'removed');
            default:
                throw new UnexpectedValueException("Unexpected object state: $objectState.".RedkingParseException::objToStr($object));
        }
    }

    /**
     * Cascades the delete operation to associated objects.
     *
     * @param object $object
     * @param array  $visited
     */
    private function cascadeRemove($object, array &$visited)
    {
        $class = $this->om->getClassMetadata(get_class($object));
        foreach ($class->fieldMappings as $mapping) {
            if (!$mapping['isCascadeRemove']) {
                continue;
            }
            if ($object instanceof Proxy && !$object->__isInitialized__) {
                $object->__load();
            }

            $relatedObjects = $class->reflFields[$mapping['fieldName']]->getValue($object);
            if (($relatedObjects instanceof Collection || is_array($relatedObjects))) {
                // If its a PersistentCollection initialization is intended! No unwrap!
                foreach ($relatedObjects as $relatedObject) {
                    $this->doRemove($relatedObject, $visited);
                }
            } elseif ($relatedObjects !== null) {
                $this->doRemove($relatedObjects, $visited);
            }
        }
    }

    /**
     * INTERNAL:
     * Schedules an object for deletion.
     *
     * @param object $object
     */
    public function scheduleForDelete($object)
    {
        $oid = spl_object_hash($object);

        if (isset($this->objectInsertions[$oid])) {
            if ($this->isInIdentityMap($object)) {
                $this->removeFromIdentityMap($object);
            }

            unset($this->objectInsertions[$oid], $this->objectStates[$oid]);

            return; // object has not been persisted yet, so nothing more to do.
        }

        if (!$this->isInIdentityMap($object)) {
            return;
        }

        $this->removeFromIdentityMap($object);

        if (isset($this->objectUpdates[$oid])) {
            unset($this->objectUpdates[$oid]);
        }

        if (!isset($this->objectDeletions[$oid])) {
            $this->objectDeletions[$oid] = $object;
            $this->objectStates[$oid] = self::STATE_REMOVED;
        }
    }

    /**
     * Schedules an object for being updated.
     *
     * @param object $object The object to schedule for being updated.
     *
     * @return void
     *
     * @throws RedkingParseException
     */
    public function scheduleForUpdate($object)
    {
        $oid = spl_object_hash($object);

        if ( ! isset($this->objectIdentifiers[$oid])) {
            throw RedkingParseException::objectHasNoIdentity($object, "scheduling for update");
        }

        if (isset($this->objectDeletions[$oid])) {
            throw RedkingParseException::objectIsRemoved($object, "schedule for update");
        }

        if ( ! isset($this->objectUpdates[$oid]) && ! isset($this->objectInsertions[$oid])) {
            $this->objectUpdates[$oid] = $object;
        }
    }

    /**
     * INTERNAL:
     * Schedules an extra update that will be executed immediately after the
     * regular object updates within the currently running commit cycle.
     *
     * Extra updates for entities are stored as (object, changeset) tuples.
     *
     * @ignore
     *
     * @param object $object    The object for which to schedule an extra update.
     * @param array  $changeset The changeset of the object (what to update).
     *
     * @return void
     */
    public function scheduleExtraUpdate($object, array $changeset)
    {
        $oid         = spl_object_hash($object);
        $extraUpdate = array($object, $changeset);

        if (isset($this->extraUpdates[$oid])) {
            list($ignored, $changeset2) = $this->extraUpdates[$oid];

            $extraUpdate = array($object, $changeset + $changeset2);
        }

        $this->extraUpdates[$oid] = $extraUpdate;
    }

    /**
     * INTERNAL:
     * Schedules a complete collection for removal when this UnitOfWork commits.
     *
     * @param PersistentCollection $coll
     *
     * @return void
     */
    public function scheduleCollectionDeletion(PersistentCollection $coll)
    {
        $coid = spl_object_hash($coll);

        //TODO: if $coll is already scheduled for recreation ... what to do?
        // Just remove $coll from the scheduled recreations?
        if (isset($this->collectionUpdates[$coid])) {
            unset($this->collectionUpdates[$coid]);
        }

        // Force emptying relation collections
        $persister = $this->getCollectionPersister($coll->getMapping());
        if ($persister instanceof Persisters\CollectionRelationPersister) {
            $persister->clearSnapShot($coll);
        }

        $this->collectionDeletions[$coid] = $coll;
    }

    /**
     * Unschedules a collection from being updated when this UnitOfWork commits.
     *
     * @internal
     */
    public function unscheduleCollectionUpdate(PersistentCollection $coll): void
    {
        if ($coll->getOwner() === null) {
            return;
        }

        $oid = spl_object_hash($coll);

        if (isset($this->collectionUpdates[$oid])) {
            unset($this->collectionUpdates[$oid]);
        }

        if (isset($this->extraUpdates[$oid])) {
            unset($this->extraUpdates[$oid]);
        }
    }

    /**
     * Gets the currently scheduled collection inserts, updates and deletes.
     *
     * @internal
     */
    public function getScheduledCollectionUpdates(): array
    {
        return $this->collectionUpdates;
    }

    /**
     * Remove from scheduled updates.
     *
     * @param mixed $object
     * 
     * @return void
     */
    public function unscheduleForUpdate($object)
    {
        $oid = spl_object_hash($object);
        if (isset($this->collectionChangeSets[$oid])) {
            unset($this->collectionChangeSets[$oid]);
        }
        if (isset($this->extraUpdates[$oid])) {
            unset($this->extraUpdates[$oid]);
        }
    }

    /**
     * Checks whether an object is scheduled for insertion.
     *
     * @param object $object
     *
     * @return bool
     */
    public function isScheduledForInsert($object)
    {
        return isset($this->objectInsertions[spl_object_hash($object)]);
    }

    /**
     * Checks whether an object is registered as removed/deleted with the unit
     * of work.
     *
     * @param object $object
     *
     * @return bool
     */
    public function isScheduledForDelete($object)
    {
        return isset($this->objectDeletions[spl_object_hash($object)]);
    }

    /**
     * Checks whether an object is registered as dirty in the unit of work.
     * Note: Is not very useful currently as dirty entities are only registered
     * at commit time.
     *
     * @param object $object
     *
     * @return bool
     */
    public function isScheduledForUpdate($object)
    {
        return isset($this->objectUpdates[spl_object_hash($object)]);
    }

    /**
     * Initializes (loads) an uninitialized persistent collection of an object.
     *
     * @param \Redking\ParseBundle\PersistentCollection $collection The collection to initialize.
     *
     * @return void
     *
     */
    public function loadCollection(PersistentCollection $collection)
    {
        $assoc     = $collection->getMapping();
        $persister = $this->getObjectPersister($assoc['targetDocument']);

        if ($assoc['implementation'] === ClassMetadata::ASSOCIATION_IMPL_RELATION) {
            $persister->loadReferenceManyCollectionFromRelation($collection);
        } else {
            if (isset($assoc['repositoryMethod']) && $assoc['repositoryMethod']) {
                $persister->loadReferenceManyWithRepositoryMethod($collection);
            } else {
                if ($assoc['isOwningSide']) {
                    $persister->loadReferenceManyCollectionOwningSide($collection);
                } else {
                    $persister->loadReferenceManyCollectionInverseSide($collection);
                }
            }
        }
        $collection->setInitialized(true);
    }

    /**
     * INTERNAL:
     * Computes the changeset of an individual object, independently of the
     * computeChangeSets() routine that is used at the beginning of a UnitOfWork#commit().
     *
     * The passed object must be a managed object. If the object already has a change set
     * because this method is invoked during a commit cycle then the change sets are added.
     * whereby changes detected in this method prevail.
     *
     * @ignore
     *
     * @param ClassMetadata $class  The class descriptor of the object.
     * @param object        $object The object for which to (re)calculate the change set.
     * @param boolean       $fromPostUpdate Tells if it is called from a PostUpdate event
     *                                      in this case, we add in extraUpdate
     *
     * @return void
     *
     * @throws RedkingParseException If the passed object is not MANAGED.
     */
    public function recomputeSingleObjectChangeSet(ClassMetadata $class, $object, $fromPostUpdate = false)
    {
        // Ignore uninitialized proxy objects
        if ($object instanceof Proxy && ! $object->__isInitialized__) {
            return;
        }

        $oid = spl_object_hash($object);

        if ( ! isset($this->objectStates[$oid]) || $this->objectStates[$oid] != self::STATE_MANAGED) {
            throw RedkingParseException::objectNotManaged($object);
        }

        // skip if change tracking is "NOTIFY"
        if ($class->isChangeTrackingNotify()) {
            return;
        }

        if ( ! $class->isInheritanceTypeNone()) {
            $class = $this->om->getClassMetadata(get_class($object));
        }

        if (isset($this->originalObjectData[$oid])) {
            $actualData = $this->cloner->copy($this->originalObjectData[$oid]);
        } else {
            throw new \RuntimeException('Cannot call recomputeSingleObjectChangeSet before computeChangeSet on an object.');
        }

        $this->applyChangesToParseObject($class, $actualData, $object);

        $originalData = $this->originalObjectData[$oid];
        $changeSet = $this->getChangesetFromParseObjects($class, $actualData, $originalData);

        // Look for changes in associations of the entity
        foreach ($class->associationMappings as $field => $assoc) {
            if (($val = $class->reflFields[$field]->getValue($object)) !== null) {
                $this->computeAssociationChanges($assoc, $val);
                if (!isset($this->objectChangeSets[$oid]) &&
                    $assoc['isOwningSide'] &&
                    $assoc['type'] == ClassMetadata::MANY &&
                    $val instanceof PersistentCollection &&
                    $val->isDirty()) {
                    $this->getCollectionPersister($assoc)->update($val);
                    $this->scheduleExtraUpdate($object, [$assoc['name'] => [$originalData->get($assoc['name']), $actualData->get($assoc['name'])]]);
                }
            }
        }

        if ($changeSet) {
            if (isset($this->objectChangeSets[$oid]) && $fromPostUpdate === false) {
                $this->objectChangeSets[$oid] = array_merge($this->objectChangeSets[$oid], $changeSet);
            } else if ( ! isset($this->objectInsertions[$oid])) {
                $this->objectChangeSets[$oid] = $changeSet;
                $this->objectUpdates[$oid]    = $object;
            }
            // apply changes on original data
            foreach ($changeSet as $key => $values) {
                if ($key === '_ACL') {
                    $this->originalObjectData[$oid]->setAcl($values);
                    continue;
                }
                if ($class->isFieldAnArray($class->getFieldNameOfName($key))) {
                    if (is_array($values[1])) {
                        $this->originalObjectData[$oid]->setArray($key, $values[1]);
                    }
                    continue;
                }
                if ($class->isFieldAnHash($class->getFieldNameOfName($key)) || $class->isFieldAnObject($class->getFieldNameOfName($key))) {
                    if (is_array($values[1])) {
                        $this->originalObjectData[$oid]->setAssociativeArray($key, $values[1]);
                    }
                    elseif (is_object($values[1])) {
                        $this->originalObjectData[$oid]->set($key, $values[1]);
                    }
                    continue;
                }

                $this->originalObjectData[$oid]->set($key, $values[1]);
            }

            if ($fromPostUpdate === false) {
                $this->scheduleForUpdate($object);
            } else {
                $this->scheduleExtraUpdate($object, $changeSet);
            }
        }
    }

    /**
     * Compute changeset between 2 ParseObjects.
     *
     * @param  ClassMetadata $class
     * @param  ParseObject   $actualData
     * @param  ParseObject   $originalData
     * @param  array         $changeSet
     * @return array
     */
    public function getChangesetFromParseObjects(ClassMetadata $class, ParseObject $actualData, ParseObject $originalData, $changeSet = [])
    {
        foreach ($class->fieldMappings as $fieldName) {
            $propName = $fieldName['name'];

            $actualValue = $actualData->get($propName);
            $orgValue = $originalData->get($propName);

            // skip if value is a number and they haven't changed
            if (in_array($fieldName['type'], [Type::FLOAT, Type::INTEGER]) && null !== $orgValue && null !== $actualValue && (abs($orgValue-$actualValue) < 0.000000000001)) {
                continue;
            }

            // skip if fields are GeoPoint and are the same
            if ($fieldName['type'] === Type::GEOPOINT
                && (
                    (null === $actualValue && null === $orgValue) ||
                    ($actualValue instanceof ParseGeoPoint && $orgValue instanceof ParseGeoPoint && $actualValue->_encode() == $orgValue->_encode())
                )
            ) {
                continue;
            }

            // skip if fields are Files and are the same
            if ($fieldName['type'] === Type::FILE
                && (
                    (null === $actualValue && null === $orgValue) ||
                    ($actualValue instanceof ParseFile && $orgValue instanceof ParseFile && $actualValue->_encode() == $orgValue->_encode())
                )
            ) {
                continue;
            }

            // skip if fields are DateTime with the same values
            if ($fieldName['type'] === Type::DATE && $actualValue instanceof \DateTime && $orgValue instanceof \DateTime
                && $actualValue->format('U') == $orgValue->format('U')) {
                continue;
            }

            // skip if updatedAt has been set but is equals as the original
            if ($propName === 'updatedAt' && $actualValue == $originalData->getUpdatedAt()) {
                continue;
            }

            // skip if value haven't changed
            if ($orgValue === $actualValue) {
                continue;
            }

            // if regular field
            if (!isset($class->associationMappings[$fieldName['fieldName']])) {
                if ($class->isChangeTrackingNotify()) {
                    continue;
                }

                $changeSet[$propName] = array($orgValue, $actualValue);

                continue;
            }

            $assoc = $class->associationMappings[$fieldName['fieldName']];

            // Persistent collection was exchanged with the "originally"
            // created one. This can only mean it was cloned and replaced
            // on another entity.
            if ($actualValue instanceof PersistentCollection) {
                $owner = $actualValue->getOwner();
                if ($owner === null) { // cloned
                    $actualValue->setOwner($object, $assoc);
                } elseif ($owner !== $object) { // no clone, we have to fix
                    if (!$actualValue->isInitialized()) {
                        $actualValue->initialize(); // we have to do this otherwise the cols share state
                    }
                    $newValue = $this->cloner->copy($actualValue);
                    $newValue->setOwner($object, $assoc);
                    $class->reflFields[$propName]->setValue($object, $newValue);
                }
            }

            if ($orgValue instanceof PersistentCollection) {
                // A PersistentCollection was de-referenced, so delete it.
                $coid = spl_object_hash($orgValue);

                if (isset($this->collectionDeletions[$coid])) {
                    continue;
                }

                $this->collectionDeletions[$coid] = $orgValue;
                $changeSet[$propName] = $orgValue; // Signal changeset, to-many assocs will be ignored.

                continue;
            }

            if ($assoc['type'] === ClassMetadata::ONE) {
                if ($assoc['isOwningSide']) {
                    // Skip if both values are the same uninitialized ParseObjects
                    if ($orgValue instanceof ParseObject &&
                        $actualValue instanceof ParseObject &&
                        $orgValue->getClassName() === $actualValue->getClassName() &&
                        $orgValue->getObjectId() === $actualValue->getObjectId()) {
                        continue;
                    }
                    $changeSet[$propName] = array($orgValue, $actualValue);
                }

                if ($orgValue instanceof ParseObject && null === $actualValue && $assoc['orphanRemoval']) {
                    $changeSet[$propName] = array($orgValue, null);
                    $this->scheduleOrphanRemoval($orgValue);
                }
            }
        }

        // Check ACL differences
        if (null !== $actualData->getAcl() && null !== $originalData->getAcl()) {
            $aclActual = $actualData->getAcl()->_encode();
            $aclOriginal = $originalData->getAcl()->_encode();
            if (!is_array($aclActual)) {
                $aclActual = [];
            }
            if (!is_array($aclOriginal)) {
                $aclOriginal = [];
            }
            $merge = array_merge($aclOriginal, $aclActual);
            if (($merge != $aclActual || $merge != $aclOriginal)) {
                $changeSet['_ACL'] = $this->cloner->copy($actualData->getAcl());
            }
        }

        return $changeSet;
    }

    /**
     * Clears the UnitOfWork.
     *
     * @param string|null $objectName if given, only objects of this type will get detached.
     */
    public function clear($objectName = null)
    {
        if ($objectName === null) {
            $this->identityMap =
            $this->objectIdentifiers =
            $this->originalObjectData =
            $this->collectionChangeSets =
            $this->objectChangeSets =
            $this->objectStates =
            $this->scheduledForDirtyCheck =
            $this->objectInsertions =
            $this->extraUpdates =
            $this->objectUpserts =
            $this->objectUpdates =
            $this->objectDeletions =
            $this->collectionUpdates =
            $this->collectionDeletions =
            $this->orphanRemovals =
            $this->hasScheduledCollections = array();
        } else {
            $visited = array();
            foreach ($this->identityMap as $className => $objects) {
                if ($className === $objectName) {
                    foreach ($objects as $object) {
                        $this->doDetach($object, $visited);
                    }
                }
            }
        }

        if ($this->evm->hasListeners(Events::onClear)) {
            $this->evm->dispatchEvent(Events::onClear, new Event\OnClearEventArgs($this->om, $objectName));
        }
    }

    /**
     * Detaches an object from the persistence management. It's persistence will
     * no longer be managed by Doctrine.
     *
     * @param object $object The object to detach.
     */
    public function detach($object)
    {
        $visited = array();
        $this->doDetach($object, $visited);
    }

    /**
     * Executes a detach operation on the given object.
     *
     * @param object $object
     * @param array $visited
     * @internal This method always considers objects with an assigned identifier as DETACHED.
     */
    private function doDetach($object, array &$visited)
    {
        $oid = spl_object_hash($object);
        if (isset($visited[$oid])) {
            return; // Prevent infinite recursion
        }

        $visited[$oid] = $object; // mark visited

        switch ($this->getObjectState($object, self::STATE_DETACHED)) {
            case self::STATE_MANAGED:
                $this->removeFromIdentityMap($object);
                unset($this->objectInsertions[$oid], $this->objectUpdates[$oid],
                    $this->objectDeletions[$oid], $this->objectIdentifiers[$oid],
                    $this->objectStates[$oid], $this->originalObjectData[$oid],
                    $this->objectUpserts[$oid], $this->hasScheduledCollections[$oid]);
                break;
            case self::STATE_NEW:
            case self::STATE_DETACHED:
                return;
        }

        $this->cascadeDetach($object, $visited);
    }

    /**
     * Cascades a detach operation to associated objects.
     *
     * @param object $object
     * @param array $visited
     */
    private function cascadeDetach($object, array &$visited)
    {
        $class = $this->om->getClassMetadata(get_class($object));
        foreach ($class->fieldMappings as $mapping) {
            if ( ! $mapping['isCascadeDetach']) {
                continue;
            }
            $relatedObjects = $class->reflFields[$mapping['fieldName']]->getValue($object);
            if (($relatedObjects instanceof Collection || is_array($relatedObjects))) {
                if ($relatedObjects instanceof PersistentCollection) {
                    // Unwrap so that foreach() does not initialize
                    $relatedObjects = $relatedObjects->unwrap();
                }
                foreach ($relatedObjects as $relatedDocument) {
                    $this->doDetach($relatedDocument, $visited);
                }
            } elseif ($relatedObjects !== null) {
                $this->doDetach($relatedObjects, $visited);
            }
        }
    }

    /**
     * Refreshes the state of the given object from the database, overwriting
     * any local, unpersisted changes.
     *
     * @param object $object The object to refresh.
     * @throws \InvalidArgumentException If the object is not MANAGED.
     */
    public function refresh($object)
    {
        $visited = array();
        $this->doRefresh($object, $visited);
    }

    /**
     * Executes a refresh operation on a object.
     *
     * @param object $object The object to refresh.
     * @param array $visited The already visited objects during cascades.
     * @throws \InvalidArgumentException If the object is not MANAGED.
     */
    private function doRefresh($object, array &$visited)
    {
        $oid = spl_object_hash($object);
        if (isset($visited[$oid])) {
            return; // Prevent infinite recursion
        }

        $visited[$oid] = $object; // mark visited

        $class = $this->om->getClassMetadata(get_class($object));

        if ($this->getObjectState($object) == self::STATE_MANAGED) {
            $id = $this->objectIdentifiers[$oid];
            $this->getObjectPersister($class->name)->refresh($id, $object);
        } else {
            throw new \InvalidArgumentException("object is not MANAGED.");
        }

        $this->cascadeRefresh($object, $visited);
    }

    /**
     * Cascades a refresh operation to associated objects.
     *
     * @param object $object
     * @param array $visited
     */
    private function cascadeRefresh($object, array &$visited)
    {
        $class = $this->om->getClassMetadata(get_class($object));

        $associationMappings = array_filter(
            $class->associationMappings,
            function ($assoc) { return $assoc['isCascadeRefresh']; }
        );

        foreach ($associationMappings as $mapping) {
            $relatedobjects = $class->reflFields[$mapping['fieldName']]->getValue($object);
            if ($relatedobjects instanceof Collection || is_array($relatedobjects)) {
                if ($relatedobjects instanceof PersistentCollection) {
                    // Unwrap so that foreach() does not initialize
                    $relatedobjects = $relatedobjects->unwrap();
                }
                foreach ($relatedobjects as $relatedobject) {
                    $this->doRefresh($relatedobject, $visited);
                }
            } elseif ($relatedobjects !== null) {
                $this->doRefresh($relatedobjects, $visited);
            }
        }
    }

    /**
     * Gets the identifier of a document.
     *
     * @param object $document
     * @return mixed The identifier value
     */
    public function getObjectIdentifier($object)
    {
        return isset($this->objectIdentifiers[spl_object_hash($object)]) ?
            $this->objectIdentifiers[spl_object_hash($object)] : null;
    }

    /**
     * Used for Doctrine fixtures
     */
    public function getEntityIdentifier(object $object): mixed
    {
        return $this->getObjectIdentifier($object);
    }

    /**
     * Returns ParseACL based on model's data.
     * 
     * @param  object $object
     * @return null|ParseACL
     */
    public function getAcl($object)
    {
        if (!method_exists($object, 'getPublicAcl')) {
            return;
        }

        $acl = $this->cloner->copy($object->getPublicAcl());
        $isPublic = false;

        if (null === $acl) {
            $acl = new ParseACL();
            $isPublic = true;
        }

        foreach ($object->getRolesAcl() as $key => $roles) {
            $isPublic = false;
            if (is_scalar($roles['role'])) {
                $acl->setRoleReadAccessWithName($roles['role'], $roles['read']);
                $acl->setRoleWriteAccessWithName($roles['role'], $roles['write']);
            } else {
                $role = $this->getOriginalObjectData($roles['role']);
                if ($role->getClassName() == '_Role') {
                    $acl->setRoleReadAccess($role, $roles['read']);
                    $acl->setRoleWriteAccess($role, $roles['write']);
                }
            }
        }

        foreach ($object->getUsersAcl() as $key => $users) {
            $isPublic = false;
            if (is_scalar($users['user'])) {
                $acl->setReadAccess($users['user'], $users['read']);
                $acl->setWriteAccess($users['user'], $users['write']);
            } else {
                $user = $this->getOriginalObjectData($users['user']);
                if ($user->getClassName() == '_User') {
                    $acl->setUserReadAccess($user, $users['read']);
                    $acl->setUserWriteAccess($user, $users['write']);
                }
            }
        }

        if ($isPublic) {
            $acl->setPublicReadAccess(true);
            $collectionName = $this->om->getClassMetadata(get_class($object))->getCollection();
            if (!in_array($collectionName, ['_User', '_Role'])) {
                $acl->setPublicWriteAccess(true);
            }
        }

        return $acl;
    }

    /**
     * Apply ParseACL on the original ParseObject.
     * Should be called before saving.
     * 
     * @param  object      $object
     * @param  ParseObject $parseObject
     * @return
     */
    public function applyAcl($object, ParseObject $parseObject = null)
    {
        if (null === $parseObject) {
            $parseObject = $this->getOriginalObjectData($object);
        }

        $acl = $this->getAcl($object);

        if (null !== $acl && null !== $parseObject) {
            $parseObject->setAcl($acl);
        }
    }

    /**
     * INTERNAL:
     * Sets a property value of the original ParseObject of an object.
     *
     * @ignore
     *
     * @param string $oid
     * @param string $property
     * @param mixed  $value
     *
     * @return void
     */
    public function setOriginalObjectProperty($oid, $property, $value)
    {
        if (isset($this->originalObjectData[$oid])) {
            $this->originalObjectData[$oid]->set($property, $value);
        }
    }

    /**
     * INTERNAL:
     * Clears the property changeset of the object with the given OID.
     *
     * @param string $oid The object's OID.
     *
     * @return void
     */
    public function clearObjectChangeSet($oid)
    {
        unset($this->objectChangeSets[$oid]);
    }

    /**
     * @todo
     */
    public function merge($object)
    {

    }

    /**
     * @todo
     */
    public function scheduleForDirtyCheck($object)
    {

    }
}
