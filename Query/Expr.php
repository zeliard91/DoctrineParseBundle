<?php

namespace Redking\ParseBundle\Query;

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
     *
     * @return self
     */
    public function operator($operator, $value)
    {
        // $this->wrapEqualityCriteria();

        if ($this->currentField) {
            $this->query[$this->currentField][$operator] = $value;
        } else {
            $this->query[$operator] = $value;
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
}
