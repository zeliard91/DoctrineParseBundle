<?php

namespace Redking\ParseBundle;

use Doctrine\Common\Collections\Criteria;
use Redking\ParseBundle\Query\Expr;

class QueryBuilder
{
    /**
     * The ObjectManager used by this QueryBuilder.
     *
     * @var ObjectManager
     */
    private $_om;

    /**
     * The ClassMetadata instance.
     *
     * @var \Doctrine\ODM\MongoDB\Mapping\ClassMetadata
     */
    private $_class;

    /**
     * Array containing the query data.
     *
     * @var array
     */
    protected $query = array('type' => Query::TYPE_FIND);

    /**
     * The current field we are operating on.
     *
     * @var string
     */
    private $currentField;

    /**
     * The Expr instance used for building this query.
     *
     * This object includes the query criteria and the "new object" used for
     * insert and update queries.
     *
     * @var Expr
     */
    protected $expr;

    /**
     * @param ObjectManager $om
     */
    public function __construct(ObjectManager $om, $objectName = null)
    {
        $this->_om = $om;
        if ($objectName !== null) {
            $this->setObjectName($objectName);
        }
        $this->expr = new Expr();
    }

    /**
     * Create a new Expr instance that can be used to build partial expressions
     * for other operator methods.
     *
     * @return Expr $expr
     */
    public function expr()
    {
        return new Expr();
    }

    /**
     * Add an $or clause to the current query.
     *
     * You can create a new expression using the {@link Builder::expr()} method.
     *
     * @see Expr::addOr()
     * @param array|Expr $expression
     * @return $this
     */
    public function addOr($expression)
    {
        $this->expr->addOr($expression);
        return $this;
    }

    /**
     * Force cloning of expr.
     *
     */
    public function __clone()
    {
        $this->expr = clone $this->expr;
    }

    /**
     * Return current query.
     *
     * @return Query
     */
    public function getQuery()
    {
        $query = $this->query;
        $query['query'] = $this->expr->getQuery();

        return new Query($this->_om, $this->_class, $query);
    }

    /**
     * @param string[]|string $objectName an array of object names or just one.
     */
    private function setObjectName($objectName)
    {
        if (is_array($objectName)) {
            $objectNames = $objectName;
            $objectName = $objectNames[0];

            $metadata = $this->_om->getClassMetadata($objectName);
            $discriminatorField = $metadata->discriminatorField;
            $discriminatorValues = $this->getDiscriminatorValues($objectNames);

            // If a defaultDiscriminatorValue is set and it is among the discriminators being queries, add NULL to the list
            if ($metadata->defaultDiscriminatorValue && (array_search($metadata->defaultDiscriminatorValue, $discriminatorValues)) !== false) {
                $discriminatorValues[] = null;
            }

            $this->field($discriminatorField)->in($discriminatorValues);
        }

        if ($objectName !== null) {
            $this->_class = $this->_om->getClassMetadata($objectName);
        }
    }

    /**
     * Change the query type to count.
     *
     * @return self
     */
    public function count()
    {
        $this->query['type'] = Query::TYPE_COUNT;
        return $this;
    }

    /**
     * Define query criteria.
     *
     * @param array $criteria [description]
     */
    public function setCriteria(array $criteria)
    {
        foreach ($criteria as $key => $value) {
            if (is_object($value) && $this->_om->getUnitOfWork()->isInIdentityMap($value)) {
                $this->field($key)->references($value);
            } else {
                $this->field($key)->equals($value);
            }
        }

        return $this;
    }

    /**
     * Set the limit for the query.
     *
     * This is only relevant for find queries and geoNear and mapReduce
     * commands.
     *
     * @see Query::prepareCursor()
     *
     * @param int $limit
     *
     * @return self
     */
    public function limit($limit)
    {
        $this->query['limit'] = (integer) $limit;

        return $this;
    }

    /**
     * Set skip for the query cursor.
     *
     * This is only relevant for find queries
     *
     * @see Query::prepareCursor()
     *
     * @param int $skip
     *
     * @return self
     */
    public function skip($skip)
    {
        $this->query['skip'] = (integer) $skip;

        return $this;
    }

    /**
     * Set one or more field/order pairs on which to sort the query.
     *
     * If sorting by multiple fields, the first argument should be an array of
     * field name (key) and order (value) pairs.
     *
     * @param array|string $fieldName Field name or array of field/order pairs
     * @param int|string   $order     Field order (if one field is specified)
     *
     * @return self
     */
    public function sort($fieldName, $order = 1)
    {
        if (!isset($this->query['sort'])) {
            $this->query['sort'] = array();
        }

        $fields = is_array($fieldName) ? $fieldName : array($fieldName => $order);

        foreach ($fields as $fieldName => $order) {
            if (is_string($order)) {
                $order = strtolower($order) === 'asc' ? 'asc' : 'desc';
            }
            if ($fieldName === 'id') {
                $this->query['sort']['objectId'] = $order;
            } else {
                $this->query['sort'][$fieldName] = $order;
            }
        }

        return $this;
    }

    /**
     * Include association.
     *
     * @param string $field
     *
     * @return self
     */
    public function includeKey($field)
    {
        if (!isset($this->query['includes'])) {
            $this->query['includes'] = [];
        }
        $this->query['includes'][] = (string) $field;

        return $this;
    }

    /**
     * Set the current field for building the expression.
     *
     * @see Expr::field()
     *
     * @param string $field
     *
     * @return self
     */
    public function field($field)
    {
        $this->expr->field((string) $field);

        return $this;
    }

    /**
     * Specify containsAll criteria for the current field.
     *
     * @see Expr::in()
     * @see https://parse.com/docs/php/guide#queries-queries-on-array-values
     *
     * @param array $values
     *
     * @return self
     */
    public function in(array $values)
    {
        $this->expr->in($values);

        return $this;
    }

    /**
     * Specify "not in" criteria for the current field.
     *
     * @see Expr::notIn()
     * @param array $values
     * @return $this
     */
    public function notIn(array $values)
    {
        $this->expr->notIn($values);

        return $this;
    }

    /**
     * Specify an equality match for the current field.
     *
     * @see Expr::equals()
     *
     * @param mixed $value
     *
     * @return self
     */
    public function equals($value)
    {
        $this->expr->equals($value);

        return $this;
    }

    /**
     * Specify $ne criteria for the current field.
     *
     * @see Expr::notEqual()
     * @param mixed $value
     * @return $this
     */
    public function notEqual($value)
    {
        $this->expr->notEqual($value);

        return $this;
    }

    /**
     * Specify "exists" criteria for the current field.
     *
     * @see Expr::exists()
     * @param boolean $bool
     * @return $this
     */
    public function exists($bool)
    {
        $this->expr->exists((boolean) $bool);

        return $this;
    }

    /**
     * @param object $object
     * @return Builder
     */
    public function references($object)
    {
        $originalObject = $this->_om->getUnitOfWork()->getOriginalObjectData($object);
        if (!is_object($originalObject)) {
            throw new \InvalidArgumentException('The object passed in reference is not from Parse DB');
        }

        $this->expr->equals($originalObject);

        return $this;
    }

    /**
     * Specify $gt criteria for the current field.
     *
     * @see Expr::gt()
     * @param mixed $value
     * @return $this
     */
    public function gt($value)
    {
        $this->expr->gt($value);
        return $this;
    }

    /**
     * Specify $gte criteria for the current field.
     *
     * @see Expr::gte()
     * @param mixed $value
     * @return $this
     */
    public function gte($value)
    {
        $this->expr->gte($value);
        return $this;
    }

    /**
     * Specify $lt criteria for the current field.
     *
     * @see Expr::lte()
     * @param mixed $value
     * @return $this
     */
    public function lt($value)
    {
        $this->expr->lt($value);
        return $this;
    }

    /**
     * Specify $lte criteria for the current field.
     *
     * @see Expr::lte()
     * @param mixed $value
     * @return $this
     */
    public function lte($value)
    {
        $this->expr->lte($value);
        return $this;
    }

    /**
     * Match a subquery to the current field.
     * 
     * @param  Query  $query
     * @return $this
     */
    public function matchQuery(Query $query)
    {
        $this->expr->matchQuery($query);
        return $this;
    }

    /**
     * Search if a string is in an attribute.
     * 
     * @param  string  $value
     * @return $this
     */
    public function contains($value)
    {
        $this->expr->contains($value);
        return $this;
    }

    /**
     * Search on an attribute with a regular expression
     * 
     * @param  string  $value
     * @param  string $modifiers Modifies the search, supports i, m
     * @return $this
     */
    public function regex($value, $modifiers = '')
    {
        $this->expr->regex($value, $modifiers);
        return $this;
    }
}
