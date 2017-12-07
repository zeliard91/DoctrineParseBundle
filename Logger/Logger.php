<?php

namespace Redking\ParseBundle\Logger;

use Psr\Log\LoggerInterface;

/**
 * A lightweight query logger.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class Logger
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $prefix;

    public function __construct(LoggerInterface $logger = null, $prefix = 'Parse query: ')
    {
        $this->logger = $logger;
        $this->prefix = $prefix;
    }

    public function logQuery(array $query)
    {
        if (null === $this->logger) {
            return;
        }

        // if (isset($query['batchInsert']) && null !== $this->batchInsertTreshold && $this->batchInsertTreshold <= $query['num']) {
        //     $query['data'] = '**'.$query['num'].' item(s)**';
        // }

        // array_walk_recursive($query, function(&$value, $key) {
        //     if ($value instanceof \MongoBinData) {
        //         $value = base64_encode($value->bin);
        //         return;
        //     }
        //     if (is_float($value) && is_infinite($value)) {
        //         $value = ($value < 0 ? '-' : '') . 'Infinity';
        //         return;
        //     }
        //     if (is_float($value) && is_nan($value)) {
        //         $value = 'NaN';
        //         return;
        //     }
        // });

        $this->logger->debug($this->prefix.json_encode($query));
    }
}
