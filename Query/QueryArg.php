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
        if ($value instanceof \DateTime) {
            $date = clone $value;
            $date->setTimeZone(new \DateTimeZone('UTC'));
            $this->value = $date;
        } else {
            $this->value = $value;
        }
        $this->argument = $argument;
    }

    /**
     * @param mixed $value
     * @return self
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
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

    /**
     * @param mixed $argument
     * @return self
     */
    public function setArgument($argument)
    {
        $this->argument = $argument;

        return $this;
    }
}
