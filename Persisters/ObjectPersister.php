<?php

namespace Redking\ParseBundle\Persisters;

use Exception;
use Redking\ParseBundle\ObjectManager;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Parse\ParseQuery;
use Parse\ParseObject;
use Parse\ParseRelation;
use Parse\ParseRole;
use Redking\ParseBundle\Exception\WrappedParseException;
use Redking\ParseBundle\PersistentCollection;

class ObjectPersister
{
    /**
     * @var \Redking\ParseBundle\ObjectManager
     */
    private $om;

    /**
     * @var \Redking\ParseBundle\UnitOfWork
     */
    private $uow;

    /**
     * @var \Redking\ParseBundle\Mapping\ClassMetadata
     */
    private $class;

    /**
     * Queued inserts.
     *
     * @var array
     */
    protected $queuedInserts = [];

    /**
     * @var array
     */
    protected $queuedUpdates = [];

    /**
     * @var array
     */
    protected $queuedDelete = [];

    public function __construct(ObjectManager $om, ClassMetadata $class)
    {
        $this->om = $om;
        $this->uow = $om->getUnitOfWork();
        $this->class = $class;
    }

    /**
     * Loads an object by a list of field criteria.
     *
     * @param array       $criteria The criteria by which to load the object.
     * @param object|null $object   The object to load the data into. If not specified, a new object is created.
     * @param array|null  $assoc    The association that connects the entity to load to another entity, if any.
     * @param array       $hints    Hints for entity creation.
     * @param int|null    $limit    Limit number of results.
     * @param array|null  $orderBy  Criteria to order by.
     *
     * @return object|null The loaded and managed entity instance or NULL if the entity can not be found.
     *
     * @todo Check identity map? loadById method? Try to guess whether $criteria is the id?
     */
    public function load(array $criteria, $object = null, $assoc = null, array $hints = array(), $limit = null, array $orderBy = null)
    {
        try {
            return $this->getQuery($criteria, $assoc, $limit, null, $orderBy)->setHints($hints)->getSingleResult();
        } catch (\Parse\ParseException $e) {
            throw new WrappedParseException($e);
        }
    }

    /**
     * Loads a list of objects by a list of field criteria.
     *
     * @param array      $criteria
     * @param array|null $orderBy
     * @param int|null   $limit
     * @param int|null   $skip
     * @param array      $hints    Hints for entity creation.
     *
     * @return array
     */
    public function loadAll(array $criteria = array(), array $orderBy = null, $limit = null, $skip = null, array $hints = array())
    {
        try {
            return $this->getQuery($criteria, null, $limit, $skip, $orderBy)->setHints($hints)->execute();
        } catch (\Parse\ParseException $e) {
            throw new WrappedParseException($e);
        }
    }

    /**
     * Returns Query.
     *
     * @return \Redking\ParseBundle\Query
     */
    protected function getQuery($criteria, $assoc = null, $limit = null, $skip = null, array $orderBy = null, array $includeKeys = null)
    {
        $qb = $this->om->createQueryBuilder($this->class->name)
            ->setCriteria($criteria);

        if (null !== $limit) {
            $qb->limit($limit);
        }
        if (null !== $skip) {
            $qb->skip($skip);
        }
        if (null !== $orderBy) {
            $qb->sort($orderBy);
        }

        if (null !== $includeKeys) {
            foreach ($includeKeys as $includeKey) {
                $qb->includeKey($includeKey);
            }
        }

        return $qb->getQuery();
    }

    /**
     * Creates or fills a single object object from an query result.
     *
     * @param object $result The query result.
     * @param object $object The object object to fill, if any.
     * @param array  $hints  Hints for object creation.
     *
     * @return object The filled and managed object object or NULL, if the query result is empty.
     */
    private function createObject($result, $object = null, array $hints = array())
    {
        if ($result === null) {
            return;
        }

        if ($object !== null) {
            $hints['doctrine.refresh'] = true;
            $id = $result->getObjectId();
            $this->uow->registerManaged($object, $id, $result);
        }

        return $this->uow->getOrCreateObject($this->class->name, $result, $hints, $object);
    }

    /**
     * Profile query.
     */
    public function profileQuery()
    {
        if (null !== $this->om->getConfiguration()->getProfilerCallable()) {
            call_user_func($this->om->getConfiguration()->getProfilerCallable());
        }
    }

    /**
     * Log query.
     *
     * @param ParseQuery $query
     */
    public function logQuery($query)
    {
        if (null !== $this->om->getConfiguration()->getLoggerCallable()) {
            $loggable_query = [];
            $loggable_query['className'] = $this->class->collection;
            if ($query instanceof ParseQuery) {
                $loggable_query += $query->_getOptions();
            } elseif (is_array($query)) {
                $loggable_query += $query;
            }

            call_user_func_array($this->om->getConfiguration()->getLoggerCallable(), array($loggable_query));
        }
    }

    /**
     * Returns new ParseObject.
     *
     * @return ParseObject
     */
    public function instanciateParseObject()
    {
        if ($this->class->collection === '_Role') {
            return new ParseRole();
        }

        return new ParseObject($this->class->collection);
    }

    /**
     * Updates the already persisted objects if it has any new changesets.
     *
     * @param ParseObject $object
     * @param array       $changeSets
     */
    public function update(ParseObject $object, array $changeSets)
    {
        $this->profileQuery();

        try {
            $object->save($this->om->isMasterRequest());
        } catch (\Parse\ParseException $e) {
            throw new WrappedParseException($e);
        }

        $this->logQuery(['type' => 'update', 'id' => $object->getObjectId(), 'changeSets' => $changeSets]);
    }

    /**
     * @param string $oid
     * @param array $changeSet
     *
     * @return void
     */
    public function addUpdate(string $oid, array $changeSet): void
    {
        $this->queuedUpdates[$oid] = $changeSet;
    }

    /**
     * Execute all queued object updates.
     *
     * @return void
     */
    public function executeUpdates(): array
    {
        if (!$this->queuedUpdates) {
            return [];
        }

        $updates = [];
        $parseObjects = [];
        $fields = [];

        foreach ($this->queuedUpdates as $oid => $changeSet) {
            $parseObject = $this->uow->getOriginalObjectDataByOid($oid);
            if (null !== $parseObject) {
                $parseObjects[$oid] = $parseObject;
                $fields[] = ['id' => $parseObject->getObjectId(), 'changeSets' => $changeSet];
            }
        }

        if (empty($parseObjects)) {
            return [];
        }

        $this->profileQuery();
        try {
            ParseObject::saveAll($parseObjects, $this->om->isMasterRequest());
        } catch (\Parse\ParseException $e) {
            $this->queuedUpdates = [];
            throw new WrappedParseException($e);
        }
        $this->logQuery(['type' => 'update', 'fields' => $fields]);

        foreach ($parseObjects as $oid => $parseObject) {
            $updates[$oid] = $parseObject->getUpdatedAt();
        }

        $this->queuedUpdates = [];

        return $updates;
    }

    /**
     * Adds an object to the queued insertions.
     * The object remains queued until {@link executeInserts} is invoked.
     *
     * @param object $object The object to queue for insertion.
     */
    public function addInsert($object)
    {
        $this->queuedInserts[spl_object_hash($object)] = $object;
    }

    /**
     * Executes all queued object insertions and returns any generated post-insert
     * identifiers that were created as a result of the insertions.
     *
     * If no inserts are queued, invoking this method is a NOOP.
     *
     * @return array An array of any generated post-insert IDs. This will be an empty array
     *               if the object class does not use the IDobject generation strategy.
     */
    public function executeInserts(): array
    {
        if (!$this->queuedInserts) {
            return [];
        }

        $inserts = [];
        $parseObjects = [];

        foreach ($this->queuedInserts as $oid => $object) {
            $this->uow->applyAcl($object);
            $parseObject = $this->uow->getOriginalObjectData($object);
            if ($parseObject === null) {
                throw new \Exception('Unable to get original data for insert');
            }
            $parseObjects[$oid] = $parseObject;
        }

        $this->profileQuery();
        $fields = json_decode('[' . implode(',', array_map(function($object): string {
            try {
                return $object->encode();
            } catch (Exception $e) {
                return json_encode(['className' => $object->getClassName()]);
            }
        }, array_values($parseObjects))) . ']');

        try {
            ParseObject::saveAll($parseObjects, $this->om->isMasterRequest());
        } catch (\Parse\ParseException $e) {
            $this->queuedInserts = [];
            throw new WrappedParseException($e);
        }

        $this->logQuery(['type' => 'insert', 'fields' => $fields]);

        foreach ($parseObjects as $oid => $parseObject) {
            $inserts[$parseObject->getObjectId()] = ['object' => $this->queuedInserts[$oid], 'parseObject' => $parseObject];
        }

        $this->queuedInserts = [];

        return $inserts;
    }

    /**
     * Remove ParseObject.
     *
     * @param ParseObject $object
     */
    public function delete(ParseObject $object)
    {
        $this->profileQuery();

        $object_id = $object->getObjectId();

        try {
            $object->destroy($this->om->isMasterRequest());
            $this->logQuery(['type' => 'remove', 'id' => $object_id]);
        } catch (\Parse\ParseException $e) {
            if ($e->getMessage() === 'Object not found.') {
                $this->logQuery(['type' => 'remove', 'id' => $object_id, 'failed reason' => $e->getMessage()]);
            } else {
                throw new WrappedParseException($e);
            }
        }
    }

    /**
     * Object object id to queue to be deleted.
     *
     * @param string $oid
     *
     * @return void
     */
    public function addDelete(string $oid): void
    {
        $this->queuedDelete[] = $oid;
    }

    /**
     * Execute batch deletion.
     *
     * @return void
     */
    public function executeDeletions(): void
    {
        if (!$this->queuedDelete) {
            return;
        }

        $parseObjects = [];
        $objectIds = [];
        foreach ($this->queuedDelete as $oid) {
            $parseObject = $this->uow->getOriginalObjectDataByOid($oid);
            if (null !== $parseObject) {
                $parseObjects[] = $parseObject;
                $objectIds[] = $parseObject->getObjectId();
            }
        }

        if (empty($parseObjects)) {
            return;
        }

        $this->profileQuery();
        try {
            ParseObject::destroyAll($parseObjects, $this->om->isMasterRequest());
            $this->logQuery(['type' => 'remove', 'ids' => $objectIds]);
        } catch (\Parse\ParseException $e) {
            $this->queuedDelete = [];
            throw new WrappedParseException($e);
        }

        $this->queuedDelete = [];
    }

    /**
     * Load objects in the inversed collection.
     *
     * @param  PersistentCollection $collection
     */
    public function loadReferenceManyCollectionInverseSide(PersistentCollection $collection)
    {
        $query = $this->createReferenceManyInverseSideQuery($collection);
        $documents = $query->execute()->toArray(false);
        foreach ($documents as $key => $document) {
            $collection->add($document);
        }
    }

    /**
     * Return Query for inversed side association.
     *
     * @param  PersistentCollection $collection
     * @return \Redking\ParseBundle\Query
     */
    public function createReferenceManyInverseSideQuery(PersistentCollection $collection)
    {
        $mapping = $collection->getMapping();

        $sort = (isset($mapping['sort'])) ? $mapping['sort'] : null;
        $limit = (isset($mapping['limit'])) ? $mapping['limit'] : null;
        $skip = (isset($mapping['skip'])) ? $mapping['skip'] : null;
        $includeKeys = (isset($mapping['includeKeys']) && !empty($mapping['includeKeys'])) ? $mapping['includeKeys'] : null;

        $owner = $collection->getOwner();
        $ownerClass = $this->om->getClassMetadata(get_class($owner));
        $targetClass = $collection->getTypeClass();
        $mappedByMapping = isset($targetClass->fieldMappings[$mapping['mappedBy']]) ? $targetClass->fieldMappings[$mapping['mappedBy']] : array();

        $objectId = $ownerClass->getIdentifierValues($owner)[$ownerClass->getIdentifier()[0]];
        $criteria = [$mappedByMapping['fieldName'] => new \Parse\ParseObject($ownerClass->collection, $objectId)];
        
        return $this->getQuery($criteria, null, $limit, $skip, $sort, $includeKeys);
    }

    /**
     * Load objects in the collection.
     *
     * @param  PersistentCollection $collection
     */
    public function loadReferenceManyCollectionOwningSide(PersistentCollection $collection)
    {
        $owner = $collection->getOwner();
        $mapping = $collection->getMapping();
        $originalData = $this->om->getUnitOfWork()->getOriginalObjectData($owner);
        $fieldName = $mapping['name'];
        
        $parseReferences = $originalData->get($fieldName);

        if (!is_array($parseReferences)) {
            return;
        }
        
        foreach ($parseReferences as $parseReference) {
            $reference = $this->om->getReference($mapping['targetDocument'], $parseReference->getObjectId(), $parseReference);
            $collection->add($reference);
        }
    }

    /**
     * Load a referebce object.
     *
     * @param  string $fieldName
     * @param  object $object
     * @return object|null
     */
    public function loadReference($fieldName, $object)
    {
        return $this->om->createQueryBuilder($this->class->name)
            ->field($fieldName)->references($object)
            ->getQuery()
            ->getSingleResult()
        ;
    }

    /**
     * Checks whether the given managed object exists in the database.
     *
     * @param object $object
     * @return boolean TRUE if the object exists in the database, FALSE otherwise.
     */
    public function exists($object)
    {
        $id = $this->class->getIdentifierObject($object);

        return (boolean) $this->load(['_objectId' => $id]);
    }
    
    /**
     * Load objects in the collection from a ParseRelation
     *
     * @param  PersistentCollection $collection
     */
    public function loadReferenceManyCollectionFromRelation(PersistentCollection $collection)
    {
        $owner = $collection->getOwner();
        $mapping = $collection->getMapping();
        $originalData = $this->om->getUnitOfWork()->getOriginalObjectData($owner);
        $fieldName = $mapping['name'];

        $relation = $originalData->get($fieldName);
        
        if (!$mapping['isOwningSide']) {
            $query = $this->getQueryForInversedRelation($collection);
        } else {
            if (!$relation instanceof ParseRelation) {
                return;
            }

            $query = $this->getQueryForRelation($collection);
        }

        $objects = $query->execute()->toArray(false);
        
        foreach ($objects as $object) {
            $collection->add($object);
        }
    }

    /**
     * Build Query to load a ParseRelation
     * @param  PersistentCollection $collection
     * @return Query
     */
    public function getQueryForRelation(PersistentCollection $collection)
    {
        $owner = $collection->getOwner();
        $mapping = $collection->getMapping();
        $originalData = $this->om->getUnitOfWork()->getOriginalObjectData($owner);

        $sort = (isset($mapping['sort'])) ? $mapping['sort'] : null;
        $limit = (isset($mapping['limit'])) ? $mapping['limit'] : null;
        $skip = (isset($mapping['skip'])) ? $mapping['skip'] : null;

        $qb = $this->om->createQueryBuilder($this->class->name);

        $qb->relatedTo('key', $mapping['fieldName']);
        $qb->relatedTo('object', $originalData->_toPointer());

        if (null !== $limit) {
            $qb->limit($limit);
        }
        if (null !== $skip) {
            $qb->skip($skip);
        }
        if (null !== $sort) {
            $qb->sort($sort);
        }

        return $qb->getQuery();
    }

    public function getQueryForInversedRelation(PersistentCollection $collection)
    {
        $owner = $collection->getOwner();
        $mapping = $collection->getMapping();
        $originalData = $this->om->getUnitOfWork()->getOriginalObjectData($owner);

        $sort = (isset($mapping['sort'])) ? $mapping['sort'] : null;
        $limit = (isset($mapping['limit'])) ? $mapping['limit'] : null;
        $skip = (isset($mapping['skip'])) ? $mapping['skip'] : null;

        $qb = $this->om->createQueryBuilder($this->class->name);

        $qb->field($mapping['mappedBy'])->equals($originalData);

        if (null !== $limit) {
            $qb->limit($limit);
        }
        if (null !== $skip) {
            $qb->skip($skip);
        }
        if (null !== $sort) {
            $qb->sort($sort);
        }

        return $qb->getQuery();
    }

    /**
     * Refreshes a managed object.
     *
     * @param string $id The identifier of the object.
     * @param object $object The object to refresh.
     */
    public function refresh($id, $object)
    {
        $this->load(['id' => $id], $object, null, ['doctrine.refresh' => true]);
    }

}
