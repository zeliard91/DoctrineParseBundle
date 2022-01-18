<?php

namespace Redking\ParseBundle;

use Doctrine\Persistence\ObjectRepository as BaseObjectRepository;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Redking\ParseBundle\Exception\RedkingParseException;

class ObjectRepository implements BaseObjectRepository, Selectable
{
    /**
     * @var string
     */
    protected $_objectName;

    /**
     * @var ObjectManager
     */
    protected $_om;

    /**
     * @var \Redking\ParseBundle\Mapping\ClassMetadata
     */
    protected $_class;

    /**
     * @var \Doctrine\Inflector\Inflector
     */
    private static $inflector;

    /**
     * Initializes a new <tt>ObjectRepository</tt>.
     *
     * @param ObjectManager         $dm            The ObjectManager to use.
     * @param UnitOfWork            $uow           The UnitOfWork to use.
     * @param Mapping\ClassMetadata $classMetadata The class descriptor.
     */
    public function __construct(ObjectManager $om, Mapping\ClassMetadata $class)
    {
        $this->_objectName = $class->name;
        $this->_om = $om;
        $this->_class = $class;
    }

    /**
     * Adds support for magic finders.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @throws RedkingParseException
     * @throws \BadMethodCallException If the method called is an invalid find* method
     *                                 or no find* method at all and therefore an invalid
     *                                 method call.
     *
     * @return array|object The found document/documents.
     */
    public function __call($method, $arguments)
    {
        if (substr($method, 0, 6) == 'findBy') {
            $by = substr($method, 6, strlen($method));
            $method = 'findBy';
        } elseif (substr($method, 0, 9) == 'findOneBy') {
            $by = substr($method, 9, strlen($method));
            $method = 'findOneBy';
        } else {
            throw new \BadMethodCallException(
                "Undefined method '$method'. The method name must start with ".
                'either findBy or findOneBy!'
            );
        }

        if (!isset($arguments[0])) {
            throw RedkingParseException::findByRequiresParameter($method.$by);
        }

        $fieldName = lcfirst($this->classify($by));

        if ($this->_class->hasField($fieldName)) {
            return $this->$method(array($fieldName => $arguments[0]));
        } else {
            throw RedkingParseException::invalidFindByCall($this->_objectName, $fieldName, $method.$by);
        }
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->_objectName;
    }

    /**
     * @return ObjectPersister
     */
    protected function getObjectPersister()
    {
        return $this->_om->getUnitOfWork()->getObjectPersister($this->_objectName);
    }

    /**
     * @return ObjectManager
     */
    public function getObjectManager()
    {
        return $this->_om;
    }

    /**
     * @return \Redking\ParseBundle\Mapping\ClassMetadata
     */
    public function getClassMetadata()
    {
        return $this->_class;
    }

    /**
     * Finds a document by its identifier.
     *
     * @param string|object $id The identifier
     *
     * @throws Mapping\MappingException
     * @throws LockException
     *
     * @return object The document.
     */
    public function find($id)
    {
        if ($id === null) {
            return;
        }

        /* TODO: What if the ID object has a field with the same name as the
         * class' mapped identifier field name?
         */
        if (is_array($id)) {
            list($identifierFieldName) = $this->_class->getIdentifierFieldNames();

            if (isset($id[$identifierFieldName])) {
                $id = $id[$identifierFieldName];
            }
        }

        // Check identity map first
        if ($document = $this->_om->getUnitOfWork()->tryGetById($id, $this->_class->rootEntityName)) {
            return $document; // Hit!
        }

        $criteria = array('_objectId' => $id);

        return $this->getObjectPersister()->load($criteria);
    }

    /**
     * Finds all objects in the repository.
     *
     * @return array
     */
    public function findAll()
    {
        return $this->findBy(array());
    }

    /**
     * Finds objects by a set of criteria.
     *
     * @param array    $criteria Query criteria
     * @param array    $sort     Sort array for Cursor::sort()
     * @param int|null $limit    Limit for Cursor::limit()
     * @param int|null $skip     Skip for Cursor::skip()
     *
     * @return array
     */
    public function findBy(array $criteria, array $sort = null, $limit = null, $skip = null)
    {
        return $this->getObjectPersister()->loadAll($criteria, $sort, $limit, $skip);
    }

    /**
     * Finds objects by a set of criteria without keeping them in the unitOfWork
     *
     * @param array    $criteria Query criteria
     * @param array    $sort     Sort array for Cursor::sort()
     * @param int|null $limit    Limit for Cursor::limit()
     * @param int|null $skip     Skip for Cursor::skip()
     *
     * @return array
     */
    public function findByWithoutManaging(array $criteria, array $sort = null, $limit = null, $skip = null)
    {
        return $this->getObjectPersister()->loadAll($criteria, $sort, $limit, $skip, ['doctrine.do_not_manage' => 1]);
    }

    /**
     * Finds a single object by a set of criteria.
     *
     * @param array $criteria
     *
     * @return object
     */
    public function findOneBy(array $criteria)
    {
        return $this->getObjectPersister()->load($criteria);
    }

    /**
     * Select all elements from a selectable that match the expression and
     * return a new collection containing these elements.
     *
     * @param \Doctrine\Common\Collections\Criteria $criteria
     *
     * @return \Doctrine\Common\Collections\Collection
     *
     * @todo  implement
     */
    public function matching(Criteria $criteria)
    {
        // $persister = $this->_em->getUnitOfWork()->getEntityPersister($this->_entityName);

        // return new LazyCriteriaCollection($persister, $criteria);
    }

    /**
     * Create a new QueryBuilder instance that is prepopulated for this object name.
     *
     * @return QueryBuilder $qb
     */
    public function createQueryBuilder()
    {
        return $this->_om->createQueryBuilder($this->_objectName);
    }

    private function classify($word)
    {
        if (null === self::$inflector)
        {
            self::$inflector = InflectorFactory::create()->build();
        }

        return self::$inflector->classify($word);
    }
}
