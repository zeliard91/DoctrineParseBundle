<?php

namespace Redking\ParseBundle\Exception;

/**
 * Class for all exceptions related to the Redking Parse.
 */
class RedkingParseException extends \Exception
{
    /**
     * @param string $objectName
     * @param string $fieldName
     * @param string $method
     *
     * @return RedkingParseException
     */
    public static function invalidFindByCall($objectName, $fieldName, $method)
    {
        return new self(sprintf('Invalid find by call %s::$fieldName (%s)', $objectName, $fieldName, $method));
    }

    /**
     * @return RedkingParseException
     */
    public static function detachedObjectCannotBeRemoved()
    {
        return new self('Detached object cannot be removed');
    }

    /**
     * @param object $object
     * @param string $operation
     *
     * @return RedkingParseException
     */
    static public function objectHasNoIdentity($object, $operation)
    {
        return new self("Object has no identity, therefore " . $operation ." cannot be performed. " . self::objToStr($object));
    }

    /**
     * @param object $object
     * @param string $operation
     *
     * @return ORMInvalidArgumentException
     */
    static public function objectIsRemoved($object, $operation)
    {
        return new self("Object is removed, therefore " . $operation ." cannot be performed. " . self::objToStr($object));
    }

    /**
     * @param string $state
     *
     * @return RedkingParseException
     */
    public static function invalidDocumentState($state)
    {
        return new self(sprintf('Invalid object state "%s"', $state));
    }

    /**
     * @param string $className
     *
     * @return RedkingParseException
     */
    public static function objectNotMappedToCollection($className)
    {
        return new self(sprintf('The "%s" object is not mapped to a MongoDB database collection.', $className));
    }

    /**
     * @return RedkingParseException
     */
    public static function objectManagerClosed()
    {
        return new self('The DocumentManager is closed.');
    }

    /**
     * @param string $methodName
     *
     * @return RedkingParseException
     */
    public static function findByRequiresParameter($methodName)
    {
        return new self("You need to pass a parameter to '".$methodName."'");
    }

    /**
     * @param string $objectNamespaceAlias
     *
     * @return RedkingParseException
     */
    public static function unknownObjectNamespace($objectNamespaceAlias)
    {
        return new self("Unknown Object namespace alias '$objectNamespaceAlias'.");
    }

    /**
     * @param string $className
     *
     * @return RedkingParseException
     */
    public static function cannotPersistMappedSuperclass($className)
    {
        return new self('Cannot persist an embedded object or mapped superclass '.$className);
    }

    /**
     * @param string $className
     * @param string $unindexedFields
     *
     * @return RedkingParseException
     */
    public static function queryNotIndexed($className, $unindexedFields)
    {
        return new self(sprintf(
            'Cannot execute unindexed queries on %s. Unindexed fields: %s',
            $className,
            implode(', ', $unindexedFields)
        ));
    }

    /**
     * @param string $className
     *
     * @return RedkingParseException
     */
    public static function invalidObjectRepository($className)
    {
        return new self("Invalid repository class '".$className."'. It must be a Redking\ParseBundle\ObjectRepository.");
    }

    /**
     * @param string       $type
     * @param string|array $expected
     * @param mixed        $got
     *
     * @return RedkingParseException
     */
    public static function invalidValueForType($type, $expected, $got)
    {
        if (is_array($expected)) {
            $expected = sprintf(
                '%s or %s',
                implode(', ', array_slice($expected, 0, -1)),
                end($expected)
            );
        }
        if (is_object($got)) {
            $gotType = get_class($got);
        } elseif (is_array($got)) {
            $gotType = 'array';
        } else {
            $gotType = 'scalar';
        }

        return new self(sprintf('%s type requires value of type %s, %s given', $type, $expected, $gotType));
    }

    /**
     * @param string $objectName
     * @param string $fieldName
     *
     * @return RedkingParseException
     */
    public static function nonMappedFieldInQuery($objectName, $fieldName)
    {
        return new self(sprintf('Non mapped field in query %s::%s', $objectName, $fieldName));
    }

    /**
     * Helper method to show an object as string.
     *
     * @param object $obj
     *
     * @return string
     */
    private static function objToStr($obj)
    {
        return method_exists($obj, '__toString') ? (string)$obj : get_class($obj).'@'.spl_object_hash($obj);
    }
}
