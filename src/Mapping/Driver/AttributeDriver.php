<?php

declare(strict_types=1);

namespace Redking\ParseBundle\Mapping\Driver;

use Doctrine\Common\Annotations\Reader;
use Redking\ParseBundle\Mapping\Annotations as ORM;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\Mapping\MappingException;
use Doctrine\Persistence\Mapping\ClassMetadata as PersistenceClassMetadata;
use Doctrine\Persistence\Mapping\Driver\ColocatedMappingDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

use function array_merge;
use function array_replace;
use function assert;
use function class_exists;
use function constant;
use function count;
use function is_array;
use function trigger_deprecation;

/**
 * The AtttributeDriver reads the mapping metadata from attributes.
 */
class AttributeDriver implements MappingDriver
{
    use ColocatedMappingDriver;

    /**
     * @internal this property will be private in 3.0
     *
     * @var Reader|AttributeReader
     */
    protected $reader;

    /** @param string|string[]|null $paths */
    public function __construct($paths = null, ?Reader $reader = null)
    {
        if ($reader !== null) {
            trigger_deprecation(
                'doctrine/mongodb-odm',
                '2.7',
                'Passing a $reader parameter to %s is deprecated',
                __METHOD__,
            );
        }

        $this->reader = $reader ?? new AttributeReader();

        $this->addPaths((array) $paths);
    }

    public function isTransient($className): bool
    {
        $classAttributes = $this->getClassAttributes(new ReflectionClass($className));

        foreach ($classAttributes as $attribute) {
            if ($attribute instanceof ORM\AbstractParseObject) {
                return false;
            }
        }

        return true;
    }

    public function loadMetadataForClass($className, PersistenceClassMetadata $metadata): void
    {
        assert($metadata instanceof ClassMetadata);
        $reflClass = $metadata->getReflectionClass();

        $classAttributes = $this->getClassAttributes($reflClass);

        $objectAttribute = null;
        foreach ($classAttributes as $attribute) {
            $classAttributes[$attribute::class] = $attribute;

            if ($attribute instanceof ORM\AbstractParseObject) {
                if ($objectAttribute !== null) {
                    throw MappingException::classCanOnlyBeMappedByOneAbstractParseObject($className, $objectAttribute, $attribute);
                }

                $objectAttribute = $attribute;
            }
        }

        if ($objectAttribute === null) {
            throw MappingException::classIsNotAValidDocument($className);
        }

        if ($objectAttribute instanceof ORM\MappedSuperclass) {
            $metadata->isMappedSuperclass = true;
        }

        if (isset($objectAttribute->collection)) {
            $metadata->setCollection($objectAttribute->collection);
        }

        if (isset($objectAttribute->repositoryClass)) {
            $metadata->setCustomRepositoryClass($objectAttribute->repositoryClass);
        }

        foreach ($reflClass->getProperties() as $property) {
            if (
                ($metadata->isMappedSuperclass && ! $property->isPrivate())
                ||
                ($metadata->isInheritedField($property->name) && $property->getDeclaringClass()->name !== $metadata->name)
            ) {
                continue;
            }

            $indexes        = [];
            $mapping        = ['fieldName' => $property->getName()];
            $fieldAttribute = null;

            foreach ($this->getPropertyAttributes($property) as $propertyAttribute) {
                if ($propertyAttribute instanceof ORM\AbstractField) {
                    $fieldAttribute = $propertyAttribute;
                }
            }

            if ($fieldAttribute) {
                $mapping = array_replace($mapping, (array) $fieldAttribute);
                $metadata->mapField($mapping);
            }
        }
    }

    /** @return Reader|AttributeReader */
    public function getReader()
    {
        trigger_deprecation(
            'doctrine/mongodb-odm',
            '2.4',
            '%s is deprecated with no replacement',
            __METHOD__,
        );

        return $this->reader;
    }

    /**
     * Factory method for the Attribute Driver
     *
     * @param string[]|string $paths
     *
     * @return AttributeDriver
     */
    public static function create($paths = [], ?Reader $reader = null)
    {
        return new self($paths, $reader);
    }

    /** @return object[] */
    private function getClassAttributes(ReflectionClass $class): array
    {
        if ($this->reader instanceof AttributeReader) {
            return $this->reader->getClassAttributes($class);
        }

        return $this->reader->getClassAnnotations($class);
    }

    /** @return object[] */
    private function getMethodAttributes(ReflectionMethod $method): array
    {
        if ($this->reader instanceof AttributeReader) {
            return $this->reader->getMethodAttributes($method);
        }

        return $this->reader->getMethodAnnotations($method);
    }

    /** @return object[] */
    private function getPropertyAttributes(ReflectionProperty $property): array
    {
        if ($this->reader instanceof AttributeReader) {
            return $this->reader->getPropertyAttributes($property);
        }

        return $this->reader->getPropertyAnnotations($property);
    }
}
