<?php

namespace Redking\ParseBundle\Exception;

class ParseObjectNotFoundException extends RedkingParseException
{
    public static function objectNotFound($className, $identifier)
    {
        return new self(sprintf(
            'The "%s" object with identifier %s could not be found.',
            $className, 
            json_encode($identifier)
        ));
    }
}
