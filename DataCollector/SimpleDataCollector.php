<?php

namespace Redking\ParseBundle\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\Stopwatch\Stopwatch;

class SimpleDataCollector extends DataCollector
{
    /**
     * @var array
     */
    protected $queries;

    /**
     * @var array
     */
    protected $queryTimes;

    /**
     * @var StopWatch|null
     */
    protected $stopwatch;

    public function __construct(StopWatch $stopwatch = null)
    {
        $this->queries = array();
        $this->queryTimes = array();
        $this->stopwatch = $stopwatch;
    }

    public function logQuery(array $query)
    {
        if (null !== $this->stopwatch) {
            $event = $this->stopwatch->stop('doctrine');
            $periods = $event->getPeriods();
            $queryTime = $periods[count($periods) - 1]->getDuration();
        } else {
            $queryTime = null;
        }

        $this->queries[] = $query;
        $this->queryTimes[] = $queryTime;
    }

    public function reset()
    {
        $this->data = array();
        $this->queries = array();
        $this->queryTimes = array();
    }

    public function startQuery()
    {
        if (null !== $this->stopwatch) {
            $this->stopwatch->start('doctrine', 'doctrine');
        }
    }

    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data['nb_queries'] = count($this->queries);
        $this->data['queries'] = array_map('json_encode', $this->queries);
        $this->data['query_times'] = $this->queryTimes;
    }

    public function getQueryCount()
    {
        return $this->data['nb_queries'];
    }

    public function getQueries()
    {
        return $this->data['queries'];
    }

    public function getQueryTimes()
    {
        return $this->data['query_times'];
    }

    public function getName()
    {
        return 'parse';
    }
}
