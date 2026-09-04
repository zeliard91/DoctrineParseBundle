<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Redking\ParseBundle\Mapping;

use Doctrine\Persistence\Mapping\MappingException as BaseMappingException;
use Redking\ParseBundle\Mapping\Annotations\AbstractParseObject;
use ReflectionObject;

/**
 * Class for all exceptions related to the Doctrine MongoDB ODM.
 *
 * @since       1.0
 *
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 */
class MappingException extends BaseMappingException
{
    /**
     * @param string $name
     *
     * @return MappingException
     */
    public static function typeExists($name)
    {
        return new self('Type '.$name.' already exists.');
    }

    /**
     * @param string $name
     *
     * @return MappingException
     */
    public static function typeNotFound($name)
    {
        return new self('Type to be overwritten '.$name.' does not exist.');
    }

    /**
     * @param string $className
     * @param string $fieldName
     *
     * @return MappingException
     */
    public static function mappingNotFound($className, $fieldName)
    {
        return new self("No mapping found for field '$fieldName' in class '$className'.");
    }

    /**
     * @param string $document
     * @param string $fieldName
     *
     * @return MappingException
     */
    public static function duplicateFieldMapping($document, $fieldName)
    {
        return new self('Property "'.$fieldName.'" in "'.$document.'" was already declared, but it must be declared only once');
    }

    /**
     * @param string $document
     * @param string $fieldName
     *
     * @return MappingException
     */
    public static function discriminatorFieldConflict($document, $fieldName)
    {
        return new self('Discriminator field "'.$fieldName.'" in "'.$document.'" conflicts with a mapped field\'s "name" attribute.');
    }

    /**
     * Throws an exception that indicates that a class used in a discriminator map does not exist.
     * An example would be an outdated (maybe renamed) classname.
     *
     * @param string $className   The class that could not be found
     * @param string $owningClass The class that declares the discriminator map.
     *
     * @return MappingException
     */
    public static function invalidClassInDiscriminatorMap($className, $owningClass)
    {
        return new self(
            "Document class '$className' used in the discriminator map of class '$owningClass' ".
            'does not exist.'
        );
    }

    /**
     * Throws an exception that indicates a discriminator value does not exist in a map.
     *
     * @param string $value       The discriminator value that could not be found
     * @param string $owningClass The class that declares the discriminator map
     *
     * @return MappingException
     */
    public static function invalidDiscriminatorValue($value, $owningClass)
    {
        return new self("Discriminator value '$value' used in the declaration of class '$owningClass' does not exist.");
    }

    /**
     * @param string $className
     *
     * @return MappingException
     */
    public static function missingFieldName($className)
    {
        return new self("The Document class '$className' field mapping misses the 'fieldName' attribute.");
    }

    /**
     * @param string $className
     *
     * @return MappingException
     */
    public static function classIsNotAValidDocument($className)
    {
        return new self('Class '.$className.' is not a valid document or mapped super class.');
    }

    /**
     * Exception for reflection exceptions - adds the document name,
     * because there might be long classnames that will be shortened
     * within the stacktrace.
     *
     * @param string               $document          The document's name
     * @param \ReflectionException $previousException
     *
     * @return \Doctrine\ODM\MongoDB\Mapping\MappingException
     */
    public static function reflectionFailure($document, \ReflectionException $previousException)
    {
        return new self('An error occurred in '.$document, 0, $previousException);
    }

    /**
     * @param string $documentName
     *
     * @return MappingException
     */
    public static function identifierRequired($documentName)
    {
        return new self("No identifier/primary key specified for Document '$documentName'."
            .' Every Document must have an identifier/primary key.');
    }

    /**
     * @param string $className
     * @param string $fieldName
     *
     * @return MappingException
     */
    public static function missingIdentifierField($className, $fieldName)
    {
        return new self("The identifier $fieldName is missing for a query of ".$className);
    }

    /**
     * @param string $className
     *
     * @return MappingException
     */
    public static function missingIdGeneratorClass($className)
    {
        return new self("The class-option for the custom ID generator is missing in class $className.");
    }

    /**
     * @param string $className
     *
     * @return MappingException
     */
    public static function classIsNotAValidGenerator($className)
    {
        return new self("The class $className if not a valid ID generator of type AbstractIdGenerator.");
    }

    /**
     * @param string $className
     * @param string $optionName
     *
     * @return MappingException
     */
    public static function missingGeneratorSetter($className, $optionName)
    {
        return new self("The class $className is missing a setter for the option $optionName.");
    }

    /**
     * @param string $className
     * @param string $fieldName
     *
     * @return MappingException
     */
    public static function cascadeOnEmbeddedNotAllowed($className, $fieldName)
    {
        return new self("Cascade on $className::$fieldName is not allowed.");
    }

    /**
     * @param string $className
     * @param string $fieldName
     *
     * @return MappingException
     */
    public static function simpleReferenceRequiresTargetDocument($className, $fieldName)
    {
        return new self("Target document must be specified for simple reference: $className::$fieldName");
    }

    /**
     * @param string $targetDocument
     *
     * @return MappingException
     */
    public static function simpleReferenceMustNotTargetDiscriminatedDocument($targetDocument)
    {
        return new self("Simple reference must not target document using Single Collection Inheritance, $targetDocument targeted.");
    }

    /**
     * @param string $strategy
     * @param string $className
     * @param string $fieldName
     *
     * @return MappingException
     */
    public static function atomicCollectionStrategyNotAllowed($strategy, $className, $fieldName)
    {
        return new self("$strategy collection strategy can be used only in top level document, used in $className::$fieldName");
    }

    /**
     * @param string $className
     * @param string $fieldName
     *
     * @return MappingException
     */
    public static function owningAndInverseReferencesRequireTargetDocument($className, $fieldName)
    {
        return new self("Target document must be specified for owning/inverse sides of reference: $className::$fieldName");
    }

    /**
     * @param string $className
     * @param string $fieldName
     *
     * @return MappingException
     */
    public static function mustNotChangeIdentifierFieldsType($className, $fieldName)
    {
        return new self("$className::$fieldName was declared an identifier and must stay this way.");
    }

    /**
     * @param string $className
     * @param string $fieldName
     * @param string $strategy
     *
     * @return MappingException
     */
    public static function referenceManySortMustNotBeUsedWithNonSetCollectionStrategy($className, $fieldName, $strategy)
    {
        return new self("ReferenceMany's sort can not be used with addToSet and pushAll strategies, $strategy used in $className::$fieldName");
    }

    /**
     * @param string $className
     * @param string $methodName
     *
     * @return MappingException
     */
    public static function lifecycleCallbackMethodNotFound($className, $methodName)
    {
        return new self("Object '" . $className . "' has no method '" . $methodName . "' to be registered as lifecycle callback.");
    }

    /**
     * @param string $listenerName
     * @param string $className
     *
     * @return MappingException
     */
    public static function objectListenerClassNotFound($listenerName, $className)
    {
        return new self(sprintf('Object Listener "%s" declared on "%s" not found.', $listenerName, $className));
    }

    /**
     * @param string $listenerName
     * @param string $methodName
     * @param string $className
     *
     * @return MappingException
     */
    public static function objectListenerMethodNotFound($listenerName, $methodName, $className)
    {
        return new self(sprintf('Object Listener "%s" declared on "%s" has no method "%s".', $listenerName, $className, $methodName));
    }

    public static function classCanOnlyBeMappedByOneAbstractParseObject(string $className, AbstractParseObject $mappedAs, AbstractParseObject $offending): self
    {
        return new self(sprintf(
            "Can not map class '%s' as %s because it was already mapped as %s.",
            $className,
            (new ReflectionObject($offending))->getShortName(),
            (new ReflectionObject($mappedAs))->getShortName(),
        ));
    }

    /**
     * @param string $className
     *
     * @return MappingException
     */
    public static function indexKeysRequired($className)
    {
        return new self(sprintf('An index declared on "%s" has no key.', $className));
    }

    /**
     * @param string     $className
     * @param string     $fieldName
     * @param mixed      $order
     *
     * @return MappingException
     */
    public static function invalidIndexOrder($className, $fieldName, $order)
    {
        return new self(sprintf(
            'Invalid index order "%s" for field "%s" of "%s". Expected one of asc, desc, 1, -1, text, 2d, 2dsphere, hashed.',
            is_scalar($order) ? (string) $order : gettype($order),
            $fieldName,
            $className
        ));
    }

    /**
     * @param string $className
     * @param string $option
     *
     * @return MappingException
     */
    public static function unsupportedIndexOption($className, $option)
    {
        return new self(sprintf(
            'Index option "%s" declared on "%s" is not supported: the Parse schema API only carries the MongoDB key '
            .'specification of an index. Only the "name" option is allowed. Options such as unique, sparse or TTL have '
            .'to be created directly in MongoDB.',
            $option,
            $className
        ));
    }

    /**
     * @param string $className
     * @param string $name
     *
     * @return MappingException
     */
    public static function duplicateIndexName($className, $name)
    {
        return new self(sprintf(
            'Two different indexes of "%s" resolve to the name "%s". Give one of them an explicit name.',
            $className,
            $name
        ));
    }

    /**
     * @param string $className
     * @param string $name
     *
     * @return MappingException
     */
    public static function indexNameTooLong($className, $name)
    {
        return new self(sprintf(
            'The generated name "%s" for an index of "%s" exceeds 127 characters. Give the index an explicit name.',
            $name,
            $className
        ));
    }

    /**
     * @param string $className
     * @param string $fieldName
     *
     * @return MappingException
     */
    public static function indexOnUnmappedField($className, $fieldName)
    {
        return new self(sprintf(
            'Property "%s" of "%s" declares an index but has no field mapping.',
            $fieldName,
            $className
        ));
    }

    /**
     * @param string $className
     * @param string $fieldName
     *
     * @return MappingException
     */
    public static function indexFieldNotMapped($className, $fieldName)
    {
        return new self(sprintf(
            'An index of "%s" refers to the field "%s" which is not mapped.',
            $className,
            $fieldName
        ));
    }

    /**
     * @param string $className
     * @param string $fieldName
     *
     * @return MappingException
     */
    public static function cannotIndexIdentifier($className, $fieldName)
    {
        return new self(sprintf(
            'The identifier "%s" of "%s" can not be indexed: it is stored as the MongoDB "_id" column, which is always '
            .'indexed and is rejected by the Parse schema API.',
            $fieldName,
            $className
        ));
    }

    /**
     * @param string $className
     * @param string $fieldName
     *
     * @return MappingException
     */
    public static function cannotIndexTimestampField($className, $fieldName)
    {
        return new self(sprintf(
            'The field "%s" of "%s" can not be indexed through the Parse schema API: it is stored as the MongoDB '
            .'"_created_at"/"_updated_at" column, and Parse only accepts index keys matching a schema field. Create '
            .'such an index directly in MongoDB, Parse Server will pick it up on its next start.',
            $fieldName,
            $className
        ));
    }

    /**
     * @param string $className
     * @param string $fieldName
     *
     * @return MappingException
     */
    public static function cannotIndexInverseSideReference($className, $fieldName)
    {
        return new self(sprintf(
            'The reference "%s" of "%s" can not be indexed: it is the inverse side of the association and has no '
            .'column in the Parse class.',
            $fieldName,
            $className
        ));
    }

    /**
     * @param string $className
     * @param string $fieldName
     *
     * @return MappingException
     */
    public static function cannotIndexRelation($className, $fieldName)
    {
        return new self(sprintf(
            'The reference "%s" of "%s" can not be indexed: it is implemented as a Parse Relation, whose data lives in '
            .'a separate "_Join" collection instead of a column of the class.',
            $fieldName,
            $className
        ));
    }
}
