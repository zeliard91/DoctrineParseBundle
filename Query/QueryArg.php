<?php

namespace Redking\ParseBundle\Query;

class QueryArg
{
    /**
     * @var mixed
     */
    protected $value;

    /**
     * @var mixed
     */
    protected $argument;

    /**
     * @param mixed $value
     * @param mixed $argument
     */
    public function __construct($value, $argument = null)
    {
        $this->value = $value;
        $this->argument = $argument;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return mixed
     */
    public function getArgument()
    {
        return $this->argument;
    }
}