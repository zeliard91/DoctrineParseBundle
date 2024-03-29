<?php

namespace Redking\ParseBundle;

use Doctrine\Common\Collections\ArrayCollection;
use Parse\ParseObject;
use Parse\ParseQuery;
use Parse\ParseUser;
use Redking\ParseBundle\Exception\RedkingParseException;
use Redking\ParseBundle\Mapping\ClassMetadata;

class Query
{
    const TYPE_FIND = 1;
    const TYPE_FIND_AND_UPDATE = 2;
    const TYPE_FIND_AND_REMOVE = 3;
    const TYPE_INSERT = 4;
    const TYPE_UPDATE = 5;
    const TYPE_REMOVE = 6;
    const TYPE_GROUP = 7;
    const TYPE_MAP_REDUCE = 8;
    const TYPE_DISTINCT = 9;
    const TYPE_GEO_NEAR = 10;
    const TYPE_COUNT = 11;
    const TYPE_AGGREGATE = 12;

    /**
     * The ObjectManager used by this QueryBuilder.
     *
     * @var ObjectManager
     */
    private $_om;

    /**
     * The ClassMetadata instance.
     *
     * @var \Redking\ParseBundle\Mapping\ClassMetadata
     */
    private $_class;

    /**
     * The Parse Query.
     *
     * @var ParseQuery
     */
    private $_parseQuery;

    /**
     * Query type.
     *
     * @var int
     */
    protected $type = self::TYPE_FIND;

    /**
     * Whether to hydrate results as object class instances.
     *
     * @var bool
     */
    private $hydrate = true;

    /**
     * Hints for UnitOfWork behavior.
     *
     * @var array
     */
    private $unitOfWorkHints = array();

    /**
     * Query structure generated by the Builder class.
     *
     * @var array
     */
    private $query;

    /**
     * @param ObjectManager $om
     * @param ClassMetadata $class
     * @param array         $query
     */
    public function __construct(ObjectManager $om, ClassMetadata $class, array $query)
    {
        switch ($query['type']) {
            case self::TYPE_FIND:
            case self::TYPE_FIND_AND_UPDATE:
            case self::TYPE_FIND_AND_REMOVE:
            case self::TYPE_INSERT:
            case self::TYPE_UPDATE:
            case self::TYPE_REMOVE:
            case self::TYPE_GROUP:
            case self::TYPE_MAP_REDUCE:
            case self::TYPE_DISTINCT:
            case self::TYPE_GEO_NEAR:
            case self::TYPE_COUNT:
            case self::TYPE_AGGREGATE:
                break;

            default:
                throw new \InvalidArgumentException('Invalid query type: '.$query['type']);
        }

        $this->_om = $om;
        $this->_class = $class;
        $this->query = $query;
        $this->type = $query['type'];
        $this->_parseQuery = new ParseQuery($this->_class->getCollection());
    }

    /**
     * @param array $hints
     */
    public function setHints(array $hints)
    {
        $this->unitOfWorkHints = $hints;

        return $this;
    }

    /**
     * Return query type.
     *
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set query type.
     *
     * @param $type integer
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Sets whether or not to hydrate the documents to objects.
     *
     * @param bool $hydrate
     */
    public function setHydrate($hydrate)
    {
        $this->hydrate = (boolean) $hydrate;
    }

    /**
     * Start profiling query.
     */
    private function profileQuery()
    {
        if (null !== $this->_om->getConfiguration()->getProfilerCallable()) {
            call_user_func($this->_om->getConfiguration()->getProfilerCallable());
        }
    }

    /**
     * Log query.
     */
    private function logQuery()
    {
        if (null !== $this->_om->getConfiguration()->getLoggerCallable()) {
            call_user_func_array($this->_om->getConfiguration()->getLoggerCallable(), array($this->toArray()));
        }
    }

    /**
     * Export Query to array.
     *
     * @return array
     */
    public function toArray()
    {
        $loggable_query = [];
        $loggable_query['className'] = $this->_class->getCollection();
        $query = $this->getParseQuery()->_getOptions();

        if (isset($query['where'])) {
            foreach ($query['where'] as $key => &$value) {
                if ($value instanceof ParseObject) {
                    $value = $value->getClassName().'.'.$value->getObjectId();
                }
            }
        }
        if (isset($this->query['query']['$aggregate'])) {
            $query['aggregate'] = $this->query['query']['$aggregate'];
        }

        $loggable_query += $query;

        return $loggable_query;
    }

    /**
     * Execute query
     *
     * @return ArrayCollection|array|int
     */
    public function execute()
    {
        $this->profileQuery();
        $this->applyQuery();

        $uow = $this->_om->getUnitOfWork();

        switch ($this->getType()) {
            case self::TYPE_FIND:
                $results = $this->_parseQuery->find($this->_om->isMasterRequest());
                $this->logQuery();

                if ($this->hydrate === false) {
                    return $results;
                }

                $nb_results = count($results);
                for ($i=0; $i < $nb_results; $i++) {
                    $results[$i] = $uow->getOrCreateObject($this->_class->name, $results[$i], $this->unitOfWorkHints);
                }

                return new ArrayCollection($results);
                break;

            case self::TYPE_COUNT:
                $nb_results = $this->_parseQuery->count($this->_om->isMasterRequest());
                $this->logQuery();
                return $nb_results;

                break;

            case self::TYPE_AGGREGATE:
                $results = $this->_parseQuery->aggregate($this->query['query']['$aggregate']);
                $this->logQuery();
                return $results;

                break;
            default:
                throw new \Exception('Unknown query type '.$this->getType());
                break;
        }
    }

    /**
     * Translate query to ParseQuery.
     *
     * @return void
     */
    protected function applyQuery()
    {
        if (isset($this->query['query']) && is_array($this->query['query'])) {
            // If there is a "or" statement, do it first to replace current ParseQuery
            foreach ($this->query['query'] as $attribute => $operations) {
                if ($attribute === '$or' && is_array($operations)) {
                    $queries = [];
                    foreach ($operations as $operation) {
                        $query = new Query($this->_om, $this->_class, ['type' => $this->type, 'query' => $operation]);
                        $queries[] = $query->getParseQuery();
                    }
                    $this->_parseQuery = $this->_parseQuery->orQueries($queries);
                }
            }

            foreach ($this->query['query'] as $attribute => $operations) {
                if ($attribute === '_objectId') {
                    $field = 'objectId';
                } 
                // If a field has the notation "attribute.id", we extract the attribute name and forge the value
                elseif (preg_match('/(.+)\.id$/', $attribute, $matches) === 1) {
                    $field = $this->_class->getNameOfField($matches[1]);
                    
                    // Try to find target collection name
                    $targetClass = $this->_class->getAssociationTargetClass($matches[1]);
                    $targetCollection = $this->_om->getClassMetadata($targetClass)->getCollection();
                    foreach ($operations as $operator => &$queryArg) {
                        if (is_string($queryArg->getValue()) && !empty($queryArg->getValue())) {
                            $queryArg->setValue((object)['__type' => 'Pointer', 'className' => $targetCollection, 'objectId' => $queryArg->getValue()]);
                        } elseif (is_iterable($queryArg->getValue())) {
                            $queryArgValues = [];
                            foreach ($queryArg->getValue() as $queryArgValue) {
                                if (is_string($queryArgValue) && !empty($queryArgValue)) {
                                    $queryArgValues[] = (object)['__type' => 'Pointer', 'className' => $targetCollection, 'objectId' => $queryArgValue];
                                }
                            }
                            if (count($queryArgValues) > 0) {
                                $queryArg->setValue($queryArgValues);
                            }
                        }
                    }
                }
                elseif (in_array($attribute, ['$or', '$aggregate'])) {
                    continue;
                }
                else {
                    $field = $this->_class->getNameOfField($attribute);
                    if ($field == '_objectId') {
                        $field = 'objectId';
                    }
                }
                if (null === $field) {
                    throw RedkingParseException::nonMappedFieldInQuery($this->_class->name, $attribute);
                }
                foreach ($operations as $operator => $queryArg) {
                    $this->_parseQuery->$operator($field, $queryArg->getValue(), $queryArg->getArgument());
                }
            }
        }

        if (isset($this->query['sort']) && is_array($this->query['sort'])) {
            foreach ($this->query['sort'] as $attribute => $order) {
                $field = $this->_class->getNameOfField($attribute);
                if ($attribute === 'objectId') {
                    $field = 'objectId';
                }
                if ($order === 'asc') {
                    $this->_parseQuery->addAscending($field);
                } else {
                    $this->_parseQuery->addDescending($field);
                }
            }
        }

        if (isset($this->query['limit']) && null !== $this->query['limit'] && $this->query['limit'] > 0) {
            $this->_parseQuery->limit($this->query['limit']);
        } else {
            // Force a high limit : the API has "100" as default
            $this->_parseQuery->limit(999999999999);
        }

        if (isset($this->query['skip']) && null !== $this->query['skip']) {
            $this->_parseQuery->skip($this->query['skip']);
        }

        if (isset($this->query['includes']) && is_array($this->query['includes'])) {
            foreach ($this->query['includes'] as $attribute) {
                if (strpos($attribute, '.') !== false) {
                    $parts = explode('.', $attribute);
                    $_class = $this->_class;
                    $fields = [];
                    foreach ($parts as $fieldPart) {
                        $fields[] = $_class->getNameOfField($fieldPart);
                        if ($_class->hasAssociation($fieldPart)) {
                            $_class = $this->_om->getClassMetadata($_class->getAssociationTargetClass($fieldPart));
                        }
                    }
                    if (count($fields) > 0) {
                        $this->_parseQuery->includeKey(implode('.', $fields));
                    }
                } else {
                    $field = $this->_class->getNameOfField($attribute);
                    $this->_parseQuery->includeKey($field);
                }
            }
        }

        if (isset($this->query['relatedTo']) && is_array($this->query['relatedTo'])) {
            foreach ($this->query['relatedTo'] as $key => $value) {
                $this->_parseQuery->relatedTo($key, $value);
            }
        }

    }

    /**
     * Returns first result.
     *
     * @return object
     */
    public function getSingleResult()
    {
        $this->profileQuery();
        $this->applyQuery();

        $result = $this->_parseQuery->first($this->_om->isMasterRequest());
        $this->logQuery();

        // return null if there is no result
        if (is_array($result) && count($result) === 0) {
            return null;
        }

        if ($this->hydrate === false) {
            return $result;
        }

        $uow = $this->_om->getUnitOfWork();

        return $uow->getOrCreateObject($this->_class->name, $result, $this->unitOfWorkHints);
    }

    /**
     * Returns the ParseQuery associated with this instance.
     *
     * @return \Parse\ParseQuery
     */
    public function getParseQuery()
    {
        // Initialize if empty
        if (count($this->_parseQuery->_getOptions()) == 0) {
            $this->applyQuery();
        }

        return $this->_parseQuery;
    }

    /**
     * Returns Iterator of the Query.
     *
     * @return Iterator
     */
    public function iterate()
    {
        return $this->execute()->getIterator();
    }

    /**
     * Returns Object Manager.
     *
     * @return \Redking\ParseBundle\ObjectManager
     */
    public function getObjectManager()
    {
        return $this->_om;
    }
}
