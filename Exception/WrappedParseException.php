<?php

namespace Redking\ParseBundle\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Parse\ParseException;

/**
 * Class for all exceptions issued by Parse API.
 */
class WrappedParseException extends HttpException
{
    public function __construct(ParseException $previous)
    {
        parent::__construct(502, $previous->getMessage(), $previous);
    }
}
