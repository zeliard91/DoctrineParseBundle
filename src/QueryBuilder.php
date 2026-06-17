<?php

namespace Redking\ParseBundle;

use Doctrine\Common\Collections\Criteria;
use Parse\ParseGeoPoint;
use Parse\ParseServerInfo;
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
     * @var \Redking\ParseBundle\Mapping\ClassMetadata
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
        $this->expr = new Expr($this->_om->getUnitOfWork());

        foreach ($this->_class->getLazyLoadKeys() as $key) {
            $this->includeKey($key);
        }
    }

    /**
     * Create a new Expr instance that can be used to build partial expressions
     * for other operator methods.
     *
     * @return Expr $expr
     */
    public function expr()
    {
        return new Expr($this->_om->getUnitOfWork());
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
     * Get Discriminator Values
     *
     * @param string[] $classNames
     *
     * @throws InvalidArgumentException If the number of found collections > 1.
     */
    private function getDiscriminatorValues($classNames): array
    {
        $discriminatorValues = [];
        $collections         = [];
        foreach ($classNames as $className) {
            $class = $this->_om->getClassMetadata($className);
            $discriminatorValues[] = $class->discriminatorValue;
            $key = $class->getCollection();
            $collections[$key] = $key;
        }

        if (count($collections) > 1) {
            throw new \InvalidArgumentException('Objects involved are not all mapped to the same database collection.');
        }

        return $discriminatorValues;
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
            } elseif (null === $value) {
                $this->field($key)->in([null]); // Temp fix as Parse PHP SDK convert equalTo null as exists=false
            }
            else {
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
     * Set whether to hydrate Parse results into mapped entities.
     *
     * When set to false, Query::execute() returns an array of raw
     * Parse\ParseObject instances and Query::getSingleResult() returns a
     * single ParseObject (or null). Raw results are not tracked by the
     * UnitOfWork and changes to them will not be persisted by flush().
     *
     * @return self
     */
    public function hydrate(bool $bool = true)
    {
        $this->query['hydrate'] = $bool;

        return $this;
    }

    /**
     * Restrict the Parse fields returned by the query (sets the `keys` option).
     *
     * Field names refer to entity properties; they are translated to Parse
     * column names at apply-time, consistent with sort() and includeKey().
     *
     * @return self
     */
    public function select(string ...$fields)
    {
        if (!isset($this->query['select'])) {
            $this->query['select'] = [];
        }
        foreach ($fields as $field) {
            $this->query['select'][] = $field;
        }

        return $this;
    }

    /**
     * Add constraint for parse relation.
     *
     * @param string $field
     *
     * @return self
     */
    public function relatedTo($key, $value)
    {
        if (!isset($this->query['relatedTo'])) {
            $this->query['relatedTo'] = [];
        }
        $this->query['relatedTo'][$key] = $value;

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
     * @return self
     */
    public function references($object)
    {
        $this->expr->references($object);

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


    public function aggregate(array $pipeline)
    {
        $this->query['type'] = Query::TYPE_AGGREGATE;

        if (version_compare(ParseServerInfo::getVersion(), '6.0.0') >= 0) {
            $pipeline = self::getAggregateForNewParseVersion($pipeline);
        }

        $this->expr->aggregate($pipeline);
        return $this;
    }

    /**
     * Aggregation pipeline stage names supported by Parse Server / MongoDB.
     *
     * On Parse Server < 6, the pipeline accepted bare stage names (e.g. "match")
     * and "objectId" as the id field. On Parse Server >= 6 the native MongoDB
     * syntax is expected: stage names prefixed with "$" and "_id" as the id field.
     *
     * @var string[]
     */
    private static $aggregateStageNames = [
        'project', 'group', 'match', 'lookup', 'unwind', 'sort',
        'skip', 'limit', 'count', 'facet', 'addFields', 'set', 'unset',
        'replaceRoot', 'sortByCount', 'sample', 'redact', 'graphLookup',
        'bucket', 'bucketAuto',
    ];

    /**
     * Convert a Parse Server < 6 aggregation pipeline to the native MongoDB
     * syntax expected by Parse Server >= 6.
     *
     * Two distinct rules apply and must not be mixed up:
     *  - Stage names are prefixed with "$", but ONLY when they appear as pipeline
     *    stages (top level, list elements, or inside a $lookup sub-pipeline). This
     *    prevents output fields that happen to share a stage name (e.g. a "count"
     *    field produced by a $group) from being wrongly renamed to "$count".
     *  - "objectId" keys are renamed to "_id" everywhere inside a stage body, since
     *    the Parse object id is stored as "_id" in MongoDB.
     */
    public static function getAggregateForNewParseVersion(array $pipeline): array
    {
        return self::transformAggregateStages($pipeline);
    }

    /**
     * Transform a pipeline (an associative array of stage => body, or a list of
     * single-stage documents). Only keys recognized as stage names are prefixed.
     */
    private static function transformAggregateStages(array $pipeline): array
    {
        $result = [];
        foreach ($pipeline as $key => $value) {
            if (is_int($key)) {
                // List form: each element is itself a stage document.
                $result[$key] = is_array($value) ? self::transformAggregateStages($value) : $value;
            } elseif (in_array($key, self::$aggregateStageNames, true)) {
                $newKey = '$' . $key;
                if ('lookup' === $key && is_array($value)) {
                    $result[$newKey] = self::transformLookupStage($value);
                } elseif (is_array($value)) {
                    $result[$newKey] = self::transformAggregateFields($value);
                } else {
                    $result[$newKey] = $value;
                }
            } else {
                // Unknown key at pipeline level: keep it but still apply field rules.
                $result[$key] = is_array($value) ? self::transformAggregateFields($value) : $value;
            }
        }

        return $result;
    }

    /**
     * Transform the body of a stage: rename "objectId" keys to "_id" recursively,
     * without ever prefixing stage names (output fields are preserved verbatim).
     */
    private static function transformAggregateFields(array $body): array
    {
        $result = [];
        foreach ($body as $key => $value) {
            $newKey = 'objectId' === $key ? '_id' : $key;
            $result[$newKey] = is_array($value) ? self::transformAggregateFields($value) : $value;
        }

        return $result;
    }

    /**
     * Transform a $lookup stage: its "pipeline" key holds a nested pipeline whose
     * stage names must be prefixed, while the other keys (from, localField,
     * foreignField, as, let, ...) follow the field rules.
     */
    private static function transformLookupStage(array $lookup): array
    {
        $result = [];
        foreach ($lookup as $key => $value) {
            if ('pipeline' === $key && is_array($value)) {
                $result[$key] = self::transformAggregateStages($value);
            } else {
                $newKey = 'objectId' === $key ? '_id' : $key;
                $result[$newKey] = is_array($value) ? self::transformAggregateFields($value) : $value;
            }
        }

        return $result;
    }

    public function getLimit(): ?int
    {
        return $this->query['limit'] ?? null;
    }

    public function near(ParseGeoPoint $value)
    {
        $this->expr->near($value);

        return $this;
    }

    public function withinMiles(ParseGeoPoint $value, int $maxDistance)
    {
        $this->expr->withinMiles($value, $maxDistance);

        return $this;
    }

    public function withinKilometers(ParseGeoPoint $value, int $maxDistance)
    {
        $this->expr->withinKilometers($value, $maxDistance);

        return $this;
    }

    public function withinGeoBox(ParseGeoPoint $southWest, ParseGeoPoint $northEast)
    {
        $this->expr->withinGeoBox($southWest, $northEast);

        return $this;
    }
}
