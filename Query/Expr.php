<?php

namespace Redking\ParseBundle\Query;

use Redking\ParseBundle\Query;

class Expr
{
    /**
     * The query criteria array.
     *
     * @var string
     */
    protected $query = array();

    /**
     * The current field we are operating on.
     *
     * @var string
     */
    protected $currentField;

    /**
     * Defines an operator and value on the expression.
     *
     * If there is a current field, the operator will be set on it; otherwise,
     * the operator is set at the top level of the query.
     *
     * @param string $operator
     * @param mixed  $value
     * @param mixed  $options
     *
     * @return self
     */
    public function operator($operator, $value, $options = null)
    {
        // $this->wrapEqualityCriteria();

        if ($this->currentField) {
            $this->query[$this->currentField][$operator] = new QueryArg($value, $options);
        } else {
            $this->query[$operator] = new QueryArg($value, $options);
        }

        return $this;
    }

    /**
     * Return the query criteria.
     *
     * @see Builder::getQueryArray()
     *
     * @return array
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Set the current field for building the expression.
     *
     * @see QueryBuilder::field()
     *
     * @param string $field
     *
     * @return self
     */
    public function field($field)
    {
        $this->currentField = (string) $field;

        return $this;
    }

    /**
     * Specify $in criteria for the current field.
     *
     * @see Builder::in()
     * @see https://parse.com/docs/php/guide#queries-queries-on-array-values
     *
     * @param array $values
     *
     * @return self
     */
    public function in(array $values)
    {
        return $this->operator('containedIn', array_values($values));
    }

    /**
     * Specify $nin criteria for the current field.
     *
     * @see Builder::notIn()
     * @param array $values
     * @return $this
     */
    public function notIn(array $values)
    {
        return $this->operator('notContainedIn', array_values($values));
    }

    /**
     * Specify an equality match for the current field.
     *
     * @see Builder::equals()
     *
     * @param mixed $value
     *
     * @return self
     */
    public function equals($value)
    {
        return $this->operator('equalTo', $value);
    }

    /**
     * Specify $ne criteria for the current field.
     *
     * @see Builder::notEqual()
     *
     * @param mixed $value
     *
     * @return self
     */
    public function notEqual($value)
    {
        return $this->operator('notEqualTo', $value);
    }

    /**
     * Specify $exists criteria for the current field.
     *
     * @see Builder::exists()
     * @param boolean $bool
     * @return $this
     */
    public function exists($bool)
    {
        if ($bool) {
            return $this->operator('exists', (boolean) $bool);
        }
        return $this->operator('doesNotExist', (boolean) $bool);

    }

    /**
     * Specify $gt criteria for the current field.
     *
     * @see Builder::gt()
     * @param mixed $value
     * @return $this
     */
    public function gt($value)
    {
        return $this->operator('greaterThan', $value);
    }

    /**
     * Specify $gte criteria for the current field.
     *
     * @see Builder::gte()
     * @param mixed $value
     * @return $this
     */
    public function gte($value)
    {
        return $this->operator('greaterThanOrEqualTo', $value);
    }

    /**
     * Specify $lt criteria for the current field.
     *
     * @see Builder::lte()
     * @see http://docs.mongodb.org/manual/reference/operator/lte/
     * @param mixed $value
     * @return $this
     */
    public function lt($value)
    {
        return $this->operator('lessThan', $value);
    }

    /**
     * Specify $lte criteria for the current field.
     *
     * @see Builder::lte()
     * @see http://docs.mongodb.org/manual/reference/operator/lte/
     * @param mixed $value
     * @return $this
     */
    public function lte($value)
    {
        return $this->operator('lessThanOrEqualTo', $value);
    }

    /**
     * Match a subquery to the current field.
     * 
     * @param  Query  $query
     * @return $this
     */
    public function matchQuery(Query $query)
    {
        return $this->operator('matchesQuery', $query->getParseQuery());
    }

    /**
     * Search if a string is in an attribute.
     * 
     * @param  string $value Regular expression
     * @return $this
     */
    public function contains($value)
    {
        return $this->operator('contains', $value);
    }

    /**
     * Search on an attribute with a regular expression.
     * 
     * @param  string $value     Regular expression
     * @param  string $modifiers Modifies the search, supports i, m
     * @return $this
     */
    public function regex($value, $modifiers = '')
    {
        return $this->operator('matches', $value, $modifiers);
    }

    /**
     * Add an $or clause to the current query.
     *
     * @see Builder::addOr()
     * @param array|Expr $expression
     * @return $this
     */
    public function addOr($expression)
    {
        $this->query['$or'][] = $expression instanceof Expr ? $expression->getQuery() : $expression;
        return $this;
    }


    public function aggregate(array $pipeline)
    {
        $this->query['$aggregate'] = $pipeline;
        return $this;
    }
}
