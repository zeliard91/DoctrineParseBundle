<?php

namespace Redking\ParseBundle\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Parse\ParseAggregateException;
use Parse\ParseException;

/**
 * Class for all exceptions issued by Parse API.
 */
class WrappedParseException extends HttpException
{
    public function __construct(ParseException $previous)
    {
        $message = $previous->getMessage();

        if ($previous instanceof ParseAggregateException) {
            foreach ($previous->getErrors() as $error) {
                $message .= ' [' . ($error['code'] ?? -1) . '] ';
                if (isset($error['object'])) {
                    $message .= $error['object']->getClassname() . '(' . $error['object']->get('objectId') . ') : ';
                }
                $message .= ($error['error'] ?? 'unknown error') . ' , ';
            }
        }

        parent::__construct(502, $message, $previous);
    }
}
