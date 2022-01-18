<?php

namespace Redking\ParseBundle;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Redking\ParseBundle\Mapping\ClassMetadata;
use ReturnTypeWillChange;
use Traversable;

class PersistentCollection implements Collection
{
    /**
     * A snapshot of the collection at the moment it was fetched from the database.
     * This is used to create a diff of the collection at commit time.
     *
     * @var array
     */
    private $snapshot = array();

    /**
     * The object that owns this collection.
     *
     * @var object
     */
    private $owner;

    /**
     * The association mapping the collection belongs to.
     * This is currently either a OneToManyMapping or a ManyToManyMapping.
     *
     * @var array
     */
    private $association;

    /**
     * The ObjectManager that manages the persistence of the collection.
     *
     * @var ObjectManager
     */
    private $om;

    /**
     * The name of the field on the target entities that points to the owner
     * of the collection. This is only set if the association is bi-directional.
     *
     * @var string
     */
    private $backRefFieldName;

    /**
     * The class descriptor of the collection's object type.
     *
     * @var ClassMetadata
     */
    private $typeClass;

    /**
     * Whether the collection is dirty and needs to be synchronized with the database
     * when the UnitOfWork that manages its persistent state commits.
     *
     * @var boolean
     */
    private $isDirty = false;

    /**
     * Whether the collection has already been initialized.
     *
     * @var boolean
     */
    private $initialized = true;

    /**
     * The wrapped Collection instance.
     *
     * @var Collection
     */
    private $coll;

    /**
     * Creates a new persistent collection.
     *
     * @param ObjectManager $om     The ObjectManager the collection will be associated with.
     * @param ClassMetadata $class  The class descriptor of the object type of this collection.
     * @param Collection $coll      The collection elements.
     */
    public function __construct(ObjectManager $om, ClassMetadata $class, Collection $coll)
    {
        $this->coll      = $coll;
        $this->om        = $om;
        $this->typeClass = $class;
    }

    /**
     * INTERNAL:
     * Sets the collection's owning object together with the AssociationMapping that
     * describes the association between the owner and the elements of the collection.
     *
     * @param object $object
     * @param array  $assoc
     *
     * @return void
     */
    public function setOwner($object, array $assoc)
    {
        $this->owner            = $object;
        $this->association      = $assoc;
        $this->backRefFieldName = $assoc['inversedBy'] ?: $assoc['mappedBy'];
    }

    /**
     * INTERNAL:
     * Gets the collection owner.
     *
     * @return object
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @return Mapping\ClassMetadata
     */
    public function getTypeClass()
    {
        return $this->typeClass;
    }

    /**
     * INTERNAL:
     * Adds an element to a collection during hydration. This will automatically
     * complete bidirectional associations in the case of a one-to-many association.
     *
     * @param mixed $element The element to add.
     *
     * @return void
     */
    public function hydrateAdd($element)
    {
        $this->coll->add($element);

        // If _backRefFieldName is set and its a one-to-many association,
        // we need to set the back reference.
        if ($this->backRefFieldName && $this->association['type'] === ClassMetadata::MANY) {
            // Set back reference to owner
            $this->typeClass->reflFields[$this->backRefFieldName]->setValue(
                $element, $this->owner
            );

            $this->om->getUnitOfWork()->setOriginalObjectProperty(
                spl_object_hash($element), $this->backRefFieldName, $this->owner
            );
        }
    }

    /**
     * INTERNAL:
     * Sets a keyed element in the collection during hydration.
     *
     * @param mixed $key     The key to set.
     * @param mixed $element The element to set.
     *
     * @return void
     */
    public function hydrateSet($key, $element)
    {
        $this->coll->set($key, $element);

        // If _backRefFieldName is set, then the association is bidirectional
        // and we need to set the back reference.
        if ($this->backRefFieldName && $this->association['type'] === ClassMetadata::MANY) {
            // Set back reference to owner
            $this->typeClass->reflFields[$this->backRefFieldName]->setValue(
                $element, $this->owner
            );
        }
    }

    /**
     * Initializes the collection by loading its contents from the database
     * if the collection is not yet initialized.
     *
     * @return void
     */
    public function initialize()
    {
        if ($this->initialized || ! $this->association) {
            return;
        }

        // Has NEW objects added through add(). Remember them.
        $newObjects = array();

        if ($this->isDirty) {
            $newObjects = $this->coll->toArray();
        }

        $this->coll->clear();
        $this->om->getUnitOfWork()->loadCollection($this);
        $this->takeSnapshot();

        // Reattach NEW objects added through add(), if any.
        if ($newObjects) {
            foreach ($newObjects as $obj) {
                $this->coll->add($obj);
            }

            $this->isDirty = true;
        }

        $this->initialized = true;
    }

    /**
     * INTERNAL:
     * Tells this collection to take a snapshot of its current state.
     *
     * @return void
     */
    public function takeSnapshot()
    {
        $this->snapshot = $this->coll->toArray();
        $this->isDirty  = false;
    }

    /**
     * INTERNAL:
     * Returns the last snapshot of the elements in the collection.
     *
     * @return array The last snapshot of the elements.
     */
    public function getSnapshot()
    {
        return $this->snapshot;
    }

    /**
     * INTERNAL:
     * getDeleteDiff
     *
     * @return array
     */
    public function getDeleteDiff()
    {
        return array_udiff_assoc(
            $this->snapshot,
            $this->coll->toArray(),
            function($a, $b) { return $a === $b ? 0 : 1; }
        );
    }

    /**
     * INTERNAL:
     * getInsertDiff
     *
     * @return array
     */
    public function getInsertDiff()
    {
        return array_udiff_assoc(
            $this->coll->toArray(),
            $this->snapshot,
            function($a, $b) { return $a === $b ? 0 : 1; }
        );
    }

    /**
     * INTERNAL: Gets the association mapping of the collection.
     *
     * @return array
     */
    public function getMapping()
    {
        return $this->association;
    }

    /**
     * Marks this collection as changed/dirty.
     *
     * @return void
     */
    private function changed()
    {
        if ($this->isDirty) {
            return;
        }

        $this->isDirty = true;

        if ($this->association !== null &&
            $this->association['isOwningSide'] &&
            $this->owner &&
            $this->om->getClassMetadata(get_class($this->owner))->isChangeTrackingNotify()) {
            $this->om->getUnitOfWork()->scheduleForDirtyCheck($this->owner);
        }
    }

    /**
     * Gets a boolean flag indicating whether this collection is dirty which means
     * its state needs to be synchronized with the database.
     *
     * @return boolean TRUE if the collection is dirty, FALSE otherwise.
     */
    public function isDirty()
    {
        return $this->isDirty;
    }

    /**
     * Sets a boolean flag, indicating whether this collection is dirty.
     *
     * @param boolean $dirty Whether the collection should be marked dirty or not.
     *
     * @return void
     */
    public function setDirty($dirty)
    {
        $this->isDirty = $dirty;
    }

    /**
     * Sets the initialized flag of the collection, forcing it into that state.
     *
     * @param boolean $bool
     *
     * @return void
     */
    public function setInitialized($bool)
    {
        $this->initialized = $bool;
    }

    /**
     * Checks whether this collection has been initialized.
     *
     * @return boolean
     */
    public function isInitialized()
    {
        return $this->initialized;
    }

    /**
     * {@inheritdoc}
     */
    public function first()
    {
        $this->initialize();

        return $this->coll->first();
    }

    /**
     * {@inheritdoc}
     */
    public function last()
    {
        $this->initialize();

        return $this->coll->last();
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key)
    {
        // TODO: If the keys are persistent as well (not yet implemented)
        //       and the collection is not initialized and orphanRemoval is
        //       not used we can issue a straight SQL delete/update on the
        //       association (table). Without initializing the collection.
        $this->initialize();

        $removed = $this->coll->remove($key);

        if ( ! $removed) {
            return $removed;
        }

        $this->changed();

        if ($this->association !== null &&
            $this->owner &&
            $this->association['orphanRemoval']) {
            $this->om->getUnitOfWork()->scheduleOrphanRemoval($removed);
        }

        return $removed;
    }

    /**
     * {@inheritdoc}
     */
    public function removeElement($element)
    {
        if ( ! $this->initialized && $this->association['fetch'] === ClassMetadata::FETCH_EXTRA_LAZY) {
            if ($this->coll->contains($element)) {
                return $this->coll->removeElement($element);
            }

            $persister = $this->om->getUnitOfWork()->getCollectionPersister($this->association);

            if ($persister->removeElement($this, $element)) {
                return $element;
            }

            return null;
        }

        $this->initialize();

        $removed = $this->coll->removeElement($element);

        if ( ! $removed) {
            return $removed;
        }

        $this->changed();

        if ($this->association !== null &&
            $this->association['type'] & ClassMetadata::MANY &&
            $this->owner &&
            $this->association['orphanRemoval']) {
            $this->om->getUnitOfWork()->scheduleOrphanRemoval($element);
        }

        return $removed;
    }

    /**
     * {@inheritdoc}
     */
    public function containsKey($key)
    {
        $this->initialize();

        return $this->coll->containsKey($key);
    }

    /**
     * {@inheritdoc}
     */
    public function contains($element)
    {
        if ( ! $this->initialized && $this->association['fetch'] === ClassMetadata::FETCH_EXTRA_LAZY) {
            $persister = $this->om->getUnitOfWork()->getCollectionPersister($this->association);

            return $this->coll->contains($element) || $persister->contains($this, $element);
        }

        $this->initialize();

        return $this->coll->contains($element);
    }

    /**
     * {@inheritdoc}
     */
    public function exists(\Closure $p)
    {
        $this->initialize();

        return $this->coll->exists($p);
    }

    /**
     * {@inheritdoc}
     */
    public function indexOf($element)
    {
        $this->initialize();

        return $this->coll->indexOf($element);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        if ( ! $this->initialized
            && $this->association['type'] === ClassMetadata::MANY
            && $this->association['fetch'] === ClassMetadata::FETCH_EXTRA_LAZY
            && isset($this->association['indexBy'])
        ) {
            if (!$this->typeClass->isIdentifierComposite && $this->typeClass->isIdentifier($this->association['indexBy'])) {
                return $this->om->find($this->typeClass->name, $key);
            }

            return $this->om->getUnitOfWork()->getCollectionPersister($this->association)->get($this, $key);
        }

        $this->initialize();

        return $this->coll->get($key);
    }

    /**
     * {@inheritdoc}
     */
    public function getKeys()
    {
        $this->initialize();

        return $this->coll->getKeys();
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        $this->initialize();

        return $this->coll->getValues();
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        if ( ! $this->initialized && $this->association['fetch'] === Mapping\ClassMetadata::FETCH_EXTRA_LAZY) {
            $persister = $this->om->getUnitOfWork()->getCollectionPersister($this->association);

            return $persister->count($this) + ($this->isDirty ? $this->coll->count() : 0);
        }

        $this->initialize();

        return $this->coll->count();
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value)
    {
        $this->initialize();

        $this->coll->set($key, $value);

        $this->changed();
    }

    /**
     * {@inheritdoc}
     */
    public function add($value)
    {
        $this->coll->add($value);

        $this->changed();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        $this->initialize();

        return $this->coll->isEmpty();
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Traversable
    {
        $this->initialize();

        return $this->coll->getIterator();
    }

    /**
     * {@inheritdoc}
     */
    public function map(\Closure $func)
    {
        $this->initialize();

        return $this->coll->map($func);
    }

    /**
     * {@inheritdoc}
     */
    public function filter(\Closure $p)
    {
        $this->initialize();

        return $this->coll->filter($p);
    }

    /**
     * {@inheritdoc}
     */
    public function forAll(\Closure $p)
    {
        $this->initialize();

        return $this->coll->forAll($p);
    }

    /**
     * {@inheritdoc}
     */
    public function partition(\Closure $p)
    {
        $this->initialize();

        return $this->coll->partition($p);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        $this->initialize();

        return $this->coll->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        if ($this->initialized && $this->isEmpty()) {
            return;
        }

        $uow = $this->om->getUnitOfWork();

        if ($this->association['type'] & ClassMetadata::MANY &&
            $this->association['orphanRemoval'] &&
            $this->owner) {
            // we need to initialize here, as orphan removal acts like implicit cascadeRemove,
            // hence for event listeners we need the objects in memory.
            $this->initialize();

            foreach ($this->coll as $element) {
                $uow->scheduleOrphanRemoval($element);
            }
        }

        $this->coll->clear();

        $this->initialized = true; // direct call, {@link initialize()} is too expensive

        if ($this->association['isOwningSide'] && $this->owner) {
            $this->changed();

            $uow->scheduleCollectionDeletion($this);

            $this->takeSnapshot();
        }
    }

    /**
     * Called by PHP when this collection is serialized. Ensures that only the
     * elements are properly serialized.
     *
     * @return array
     *
     * @internal Tried to implement Serializable first but that did not work well
     *           with circular references. This solution seems simpler and works well.
     */
    public function __sleep()
    {
        return array('coll', 'initialized');
    }

    /* ArrayAccess implementation */

    /**
     * {@inheritdoc}
     */
    #[ReturnTypeWillChange]
    public function offsetExists($offset): bool
    {
        $this->initialize();

        return $this->containsKey($offset);
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * {@inheritdoc}
     */
    /**
     * @param mixed $offset
     * @param mixed $value
     * 
     * @return void
     */
    #[ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if ( ! isset($offset)) {
            return $this->add($value);
        }

        return $this->set($offset, $value);
    }

    /**
     * @param mixed $offset
     * 
     * @return void
     */
    #[ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        return $this->remove($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        $this->initialize();

        return $this->coll->key();
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        $this->initialize();

        return $this->coll->current();
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->initialize();
        
        return $this->coll->next();
    }

    /**
     * Retrieves the wrapped Collection instance.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function unwrap()
    {
        return $this->coll;
    }

    /**
     * Extracts a slice of $length elements starting at position $offset from the Collection.
     *
     * If $length is null it returns all elements from $offset to the end of the Collection.
     * Keys have to be preserved by this method. Calling this method will only return the
     * selected slice and NOT change the elements contained in the collection slice is called on.
     *
     * @param int      $offset
     * @param int|null $length
     *
     * @return array
     */
    public function slice($offset, $length = null)
    {
        if ( ! $this->initialized && ! $this->isDirty && $this->association['fetch'] === ClassMetadata::FETCH_EXTRA_LAZY) {
            $persister = $this->om->getUnitOfWork()->getCollectionPersister($this->association);

            return $persister->slice($this, $offset, $length);
        }

        $this->initialize();

        return $this->coll->slice($offset, $length);
    }

    /**
     * Cleans up internal state of cloned persistent collection.
     *
     * The following problems have to be prevented:
     * 1. Added entities are added to old PC
     * 2. New collection is not dirty, if reused on other object nothing
     * changes.
     * 3. Snapshot leads to invalid diffs being generated.
     * 4. Lazy loading grabs entities from old owner object.
     * 5. New collection is connected to old owner and leads to duplicate keys.
     *
     * @return void
     */
    public function __clone()
    {
        if (is_object($this->coll)) {
            $this->coll = clone $this->coll;
        }

        $this->initialize();

        $this->owner    = null;
        $this->snapshot = array();

        $this->changed();
    }

    /**
     * Selects all elements from a selectable that match the expression and
     * return a new collection containing these elements.
     *
     * @param \Doctrine\Common\Collections\Criteria $criteria
     *
     * @return ArrayCollection
     *
     * @throws \RuntimeException
     */
    public function matching(Criteria $criteria)
    {
        if ($this->isDirty) {
            $this->initialize();
        }

        $builder         = Criteria::expr();
        $ownerExpression = $builder->eq($this->backRefFieldName, $this->owner);
        $expression      = $criteria->getWhereExpression();
        $expression      = $expression ? $builder->andX($expression, $ownerExpression) : $ownerExpression;

        $criteria->where($expression);

        $persister = $this->om->getUnitOfWork()->getObjectPersister($this->association['targetEntity']);

        return new ArrayCollection($persister->loadCriteria($criteria));
    }
}
