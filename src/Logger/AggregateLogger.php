<?php

namespace Redking\ParseBundle\Logger;

/**
 * An aggregate query logger.
 */
class AggregateLogger
{
    private $loggers;

    /**
     * Constructor.
     *
     * @param array $loggers An array of LoggerInterface objects
     */
    public function __construct(array $loggers)
    {
        $this->loggers = $loggers;
    }

    public function logQuery(array $query)
    {
        foreach ($this->loggers as $logger) {
            $logger->logQuery($query);
        }
    }
}
