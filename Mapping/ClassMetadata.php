<?php

namespace Redking\ParseBundle\Mapping;

use Doctrine\Common\Persistence\Mapping\ClassMetadata as BaseClassMetadata;
use Doctrine\Instantiator\Instantiator;

/**
 * Contract for a Doctrine persistence layer ClassMetadata class to implement.
 *
 * @link   www.doctrine-project.org
 * @since  2.1
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Jonathan Wage <jonwage@gmail.com>
 */
class ClassMetadata implements BaseClassMetadata
{
    const REFERENCE_ONE = 1;
    const REFERENCE_MANY = 2;
    const MANY = 'many';
    const ONE = 'one';

    /**
     * DEFERRED_IMPLICIT means that changes of entities are calculated at commit-time
     * by doing a property-by-property comparison with the original data. This will
     * be done for all entities that are in MANAGED state at commit-time.
     *
     * This is the default change tracking policy.
     */
    const CHANGETRACKING_DEFERRED_IMPLICIT = 1;

    /**
     * DEFERRED_EXPLICIT means that changes of entities are calculated at commit-time
     * by doing a property-by-property comparison with the original data. This will
     * be done only for entities that were explicitly saved (through persist() or a cascade).
     */
    const CHANGETRACKING_DEFERRED_EXPLICIT = 2;

    /**
     * NOTIFY means that Doctrine relies on the entities sending out notifications
     * when their properties change. Such entity classes must implement
     * the <tt>NotifyPropertyChanged</tt> interface.
     */
    const CHANGETRACKING_NOTIFY = 3;

    /* The inheritance mapping types */
    /**
     * NONE means the class does not participate in an inheritance hierarchy
     * and therefore does not need an inheritance mapping type.
     */
    const INHERITANCE_TYPE_NONE = 1;

    /**
     * Specifies that an association is to be fetched when it is first accessed.
     */
    const FETCH_LAZY = 2;

    /**
     * Specifies that an association is to be fetched when the owner of the
     * association is fetched.
     */
    const FETCH_EAGER = 3;

    /**
     * Specifies that an association is to be fetched lazy (on first access) and that
     * commands such as Collection#count, Collection#slice are issued directly against
     * the database if the collection is not yet initialized.
     */
    const FETCH_EXTRA_LAZY = 4;

    /**
     * READ-ONLY: The name of the entity class.
     *
     * @var string
     */
    public $name;

    /**
     * READ-ONLY: The field name of the document identifier.
     */
    public $identifier;

    /**
     * The ReflectionClass instance of the mapped class.
     *
     * @var ReflectionClass
     */
    public $reflClass;

    /**
     * The ReflectionProperty instances of the mapped class.
     *
     * @var \ReflectionProperty[]
     */
    public $reflFields = array();

    /**
     * @var \Doctrine\Instantiator\InstantiatorInterface|null
     */
    private $instantiator;

    /**
     * NamingStrategy determining the default column and table names.
     *
     * @var \Redking\ParseBundle\Mapping\DefaultNamingStrategy
     */
    protected $namingStrategy;

    /**
     * READ-ONLY: Whether this class describes the mapping of a mapped superclass.
     *
     * @var bool
     */
    public $isMappedSuperclass = false;

    /**
     * READ-ONLY: Whether this class describes the mapping of a embedded document.
     *
     * @var bool
     */
    public $isEmbeddedDocument = false;

    /**
     * The name of the custom repository class used for the object class.
     * (Optional).
     *
     * @var string
     */
    public $customRepositoryClassName;

    /**
     * READ-ONLY: The policy used for change-tracking on entities of this class.
     *
     * @var int
     */
    public $changeTrackingPolicy = self::CHANGETRACKING_DEFERRED_IMPLICIT;

    /**
     * Is this object marked as "read-only"?
     *
     * That means it is never considered for change-tracking in the UnitOfWork. It is a very helpful performance
     * optimization for entities that are immutable, either in your domain or through the relation database
     * (coming from a view, or a history table for example).
     *
     * @var bool
     */
    public $isReadOnly = false;

    /**
     * READ-ONLY: The inheritance mapping type used by the class.
     *
     * @var int
     */
    public $inheritanceType = self::INHERITANCE_TYPE_NONE;

    /**
     * READ-ONLY: The field mappings of the class.
     * Keys are field names and values are mapping definitions.
     *
     * The mapping definition array has the following values:
     *
     * - <b>fieldName</b> (string)
     * The name of the field in the Entity.
     *
     * - <b>type</b> (string)
     * The type name of the mapped field. Can be one of Doctrine's mapping types
     * or a custom mapping type.
     *
     * - <b>columnName</b> (string, optional)
     * The column name. Optional. Defaults to the field name.
     *
     * - <b>length</b> (integer, optional)
     * The database length of the column. Optional. Default value taken from
     * the type.
     *
     * - <b>id</b> (boolean, optional)
     * Marks the field as the primary key of the entity. Multiple fields of an
     * entity can have the id attribute, forming a composite key.
     *
     * - <b>nullable</b> (boolean, optional)
     * Whether the column is nullable. Defaults to FALSE.
     *
     * - <b>columnDefinition</b> (string, optional, schema-only)
     * The SQL fragment that is used when generating the DDL for the column.
     *
     * - <b>precision</b> (integer, optional, schema-only)
     * The precision of a decimal column. Only valid if the column type is decimal.
     *
     * - <b>scale</b> (integer, optional, schema-only)
     * The scale of a decimal column. Only valid if the column type is decimal.
     *
     * - <b>'unique'</b> (string, optional, schema-only)
     * Whether a unique constraint should be generated for the column.
     *
     * @var array
     */
    public $fieldMappings = array();

    /**
     * READ-ONLY: The association mappings of the class.
     * Keys are field names and values are mapping definitions.
     *
     * @var array
     */
    public $associationMappings = array();

    /**
     * READ-ONLY: The registered lifecycle callbacks for entities of this class.
     *
     * @var array
     */
    public $lifecycleCallbacks = array();

    /**
     * READ-ONLY: The registered entity listeners.
     *
     * @var array
     */
    public $objectListeners = array();

    /**
     * Initializes a new ClassMetadata instance that will hold the object-relational mapping
     * metadata of the class with the given name.
     *
     * @param string              $entityName     The name of the entity class the new instance is used for.
     * @param NamingStrategy|null $namingStrategy
     */
    public function __construct($entityName)
    {
        $this->name = $entityName;
        $this->rootEntityName = $entityName;
        $this->instantiator = new Instantiator();
        $this->namingStrategy = new DefaultNamingStrategy();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifier()
    {
        return array($this->identifier);
    }

    /**
     * {@inheritdoc}
     */
    public function getReflectionClass()
    {
        if (!$this->reflClass) {
            $this->reflClass = new \ReflectionClass($this->name);
        }

        return $this->reflClass;
    }

    /**
     * Gets the ReflectionProperties of the mapped class.
     *
     * @return array An array of ReflectionProperty instances.
     */
    public function getReflectionProperties()
    {
        return $this->reflFields;
    }

    /**
     * Initializes a new ClassMetadata instance that will hold the object-relational mapping
     * metadata of the class with the given name.
     *
     * @param \Doctrine\Common\Persistence\Mapping\ReflectionService $reflService The reflection service.
     */
    public function initializeReflection($reflService)
    {
        $this->reflClass = $reflService->getClass($this->name);
        $this->namespace = $reflService->getClassNamespace($this->name);

        if ($this->reflClass) {
            $this->name = $this->rootEntityName = $this->reflClass->getName();
        }
    }

    /**
     * Restores some state that can not be serialized/unserialized.
     */
    public function wakeupReflection()
    {
        // Restore ReflectionClass and properties
        $this->reflClass = new \ReflectionClass($this->name);
        $this->instantiator = $this->instantiator ?: new Instantiator();

        foreach ($this->fieldMappings as $field => $mapping) {
            if (isset($mapping['declared'])) {
                $reflField = new \ReflectionProperty($mapping['declared'], $field);
            } else {
                $reflField = $this->reflClass->getProperty($field);
            }
            $reflField->setAccessible(true);
            $this->reflFields[$field] = $reflField;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isIdentifier($fieldName)
    {
        return $this->identifier === $fieldName;
    }

    /**
     * Get the collection this Document is mapped to.
     *
     * @return string $collection The collection name.
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * Sets the collection this Document is mapped to.
     *
     * @param string $name
     *
     * @throws \InvalidArgumentException
     */
    public function setCollection($name)
    {
        $this->collection = $name;
    }

    /**
     * Registers a custom repository class for the document class.
     *
     * @param string $repositoryClassName The class name of the custom repository.
     */
    public function setCustomRepositoryClass($repositoryClassName)
    {
        if ($this->isEmbeddedDocument) {
            return;
        }

        if ($repositoryClassName && strpos($repositoryClassName, '\\') === false && strlen($this->namespace)) {
            $repositoryClassName = $this->namespace.'\\'.$repositoryClassName;
        }

        $this->customRepositoryClassName = $repositoryClassName;
    }

    /**
     * Map a field.
     *
     * @param array $mapping The mapping information.
     *
     * @return array
     *
     * @throws MappingException
     */
    public function mapField(array $mapping)
    {
        if (!isset($mapping['fieldName']) && isset($mapping['name'])) {
            $mapping['fieldName'] = $mapping['name'];
        }
        if (!isset($mapping['fieldName'])) {
            throw MappingException::missingFieldName($this->name);
        }
        if (!isset($mapping['name'])) {
            $mapping['name'] = $mapping['fieldName'];
        }
        if ($this->identifier === $mapping['name'] && empty($mapping['id'])) {
            throw MappingException::mustNotChangeIdentifierFieldsType($this->name, $mapping['name']);
        }
        if (isset($this->fieldMappings[$mapping['fieldName']])) {
            //throw MappingException::duplicateFieldMapping($this->name, $mapping['fieldName']);
        }

        if (isset($mapping['targetDocument']) && strpos($mapping['targetDocument'], '\\') === false && strlen($this->namespace)) {
            $mapping['targetDocument'] = $this->namespace.'\\'.$mapping['targetDocument'];
        }

        if (isset($mapping['discriminatorMap'])) {
            foreach ($mapping['discriminatorMap'] as $key => $class) {
                if (strpos($class, '\\') === false && strlen($this->namespace)) {
                    $mapping['discriminatorMap'][$key] = $this->namespace.'\\'.$class;
                }
            }
        }

        if (isset($mapping['cascade']) && isset($mapping['embedded'])) {
            throw MappingException::cascadeOnEmbeddedNotAllowed($this->name, $mapping['fieldName']);
        }

        $cascades = isset($mapping['cascade']) ? array_map('strtolower', (array) $mapping['cascade']) : array();

        if (in_array('all', $cascades) || isset($mapping['embedded'])) {
            $cascades = array('remove', 'persist', 'refresh', 'merge', 'detach');
        }

        if (isset($mapping['embedded'])) {
            unset($mapping['cascade']);
        } elseif (isset($mapping['cascade'])) {
            $mapping['cascade'] = $cascades;
        }

        $mapping['isCascadeRemove'] = in_array('remove', $cascades);
        $mapping['isCascadePersist'] = in_array('persist', $cascades);
        $mapping['isCascadeRefresh'] = in_array('refresh', $cascades);
        $mapping['isCascadeMerge'] = in_array('merge', $cascades);
        $mapping['isCascadeDetach'] = in_array('detach', $cascades);

        if (isset($mapping['type']) && $mapping['type'] === 'file') {
            $mapping['file'] = true;
        }
        if (isset($mapping['file']) && $mapping['file'] === true) {
            $this->file = $mapping['fieldName'];
            $mapping['name'] = 'file';
        }
        if (isset($mapping['distance']) && $mapping['distance'] === true) {
            $this->distance = $mapping['fieldName'];
        }
        if (isset($mapping['id']) && $mapping['id'] === true) {
            $mapping['name'] = '_objectId';
            $this->identifier = $mapping['fieldName'];
        }
        if (!isset($mapping['nullable'])) {
            $mapping['nullable'] = false;
        }

        if (isset($mapping['reference']) && !empty($mapping['simple']) && !isset($mapping['targetDocument'])) {
            throw MappingException::simpleReferenceRequiresTargetDocument($this->name, $mapping['fieldName']);
        }

        if (isset($mapping['reference']) && empty($mapping['targetDocument']) && empty($mapping['discriminatorMap']) &&
                (isset($mapping['mappedBy']) || isset($mapping['inversedBy']))) {
            throw MappingException::owningAndInverseReferencesRequireTargetDocument($this->name, $mapping['fieldName']);
        }

        if ($this->isEmbeddedDocument && $mapping['type'] === 'many' && CollectionHelper::isAtomic($mapping['strategy'])) {
            throw MappingException::atomicCollectionStrategyNotAllowed($mapping['strategy'], $this->name, $mapping['fieldName']);
        }

        if (isset($mapping['reference']) && $mapping['type'] === 'one') {
            $mapping['association'] = self::REFERENCE_ONE;
        }
        if (isset($mapping['reference']) && $mapping['type'] === 'many') {
            $mapping['association'] = self::REFERENCE_MANY;
        }
        if (isset($mapping['embedded']) && $mapping['type'] === 'one') {
            $mapping['association'] = self::EMBED_ONE;
        }
        if (isset($mapping['embedded']) && $mapping['type'] === 'many') {
            $mapping['association'] = self::EMBED_MANY;
        }

        if (isset($mapping['association']) && !isset($mapping['targetDocument']) && !isset($mapping['discriminatorField'])) {
            $mapping['discriminatorField'] = self::DEFAULT_DISCRIMINATOR_FIELD;
        }

        /*
        if (isset($mapping['type']) && ($mapping['type'] === 'one' || $mapping['type'] === 'many')) {
            $mapping['type'] = $mapping['type'] === 'one' ? self::ONE : self::MANY;
        }
        */
        if (isset($mapping['version'])) {
            $mapping['notSaved'] = true;
            $this->setVersionMapping($mapping);
        }
        if (isset($mapping['lock'])) {
            $mapping['notSaved'] = true;
            $this->setLockMapping($mapping);
        }
        $mapping['isOwningSide'] = true;
        $mapping['isInverseSide'] = false;
        if (isset($mapping['reference'])) {
            if (isset($mapping['inversedBy']) && $mapping['inversedBy']) {
                $mapping['isOwningSide'] = true;
                $mapping['isInverseSide'] = false;
            }
            if (isset($mapping['mappedBy']) && $mapping['mappedBy']) {
                $mapping['isInverseSide'] = true;
                $mapping['isOwningSide'] = false;
            }
            if (isset($mapping['repositoryMethod'])) {
                $mapping['isInverseSide'] = true;
                $mapping['isOwningSide'] = false;
            }
            if (!isset($mapping['orphanRemoval'])) {
                $mapping['orphanRemoval'] = false;
            }

            // Fetch mode. Default fetch mode to LAZY, if not set.
            if ( ! isset($mapping['fetch'])) {
                $mapping['fetch'] = self::FETCH_LAZY;
            }
        }

        if (isset($mapping['reference']) && $mapping['type'] === 'many' && $mapping['isOwningSide']
            && !empty($mapping['sort']) && !CollectionHelper::usesSet($mapping['strategy'])) {
            throw MappingException::referenceManySortMustNotBeUsedWithNonSetCollectionStrategy($this->name, $mapping['fieldName'], $mapping['strategy']);
        }

        $this->fieldMappings[$mapping['fieldName']] = $mapping;
        if (isset($mapping['association'])) {
            $this->associationMappings[$mapping['fieldName']] = $mapping;
        }

        return $mapping;
    }

    /**
     * Sets the parent class names.
     * Assumes that the class names in the passed array are in the order:
     * directParent -> directParentParent -> directParentParentParent ... -> root.
     *
     * @param string[] $classNames
     */
    public function setParentClasses(array $classNames)
    {
        $this->parentClasses = $classNames;

        if (count($classNames) > 0) {
            $this->rootDocumentName = array_pop($classNames);
        }
    }

    /**
     * Creates a new instance of the mapped class, without invoking the constructor.
     *
     * @return object
     */
    public function newInstance()
    {
        return $this->instantiator->instantiate($this->name);
    }

    /**
     * {@inheritdoc}
     */
    public function hasField($fieldName)
    {
        return isset($this->fieldMappings[$fieldName]);
    }

    /**
     * {@inheritdoc}
     */
    public function hasAssociation($fieldName)
    {
        return isset($this->associationMappings[$fieldName]);
    }

    /**
     * {@inheritdoc}
     */
    public function isSingleValuedAssociation($fieldName)
    {
        return isset($this->fieldMappings[$fieldName]['association']) &&
            $this->fieldMappings[$fieldName]['association'] === self::REFERENCE_ONE;
    }

    /**
     * {@inheritdoc}
     */
    public function isCollectionValuedAssociation($fieldName)
    {
        return isset($this->fieldMappings[$fieldName]['association']) &&
            $this->fieldMappings[$fieldName]['association'] === self::REFERENCE_MANY;
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldNames()
    {
        return array_keys($this->fieldMappings);
    }

    /**
     * Gets the mapping of a field.
     *
     * @param string $fieldName The field name.
     *
     * @return array The field mapping.
     *
     * @throws MappingException if the $fieldName is not found in the fieldMappings array
     * @throws MappingException
     */
    public function getFieldMapping($fieldName)
    {
        if (!isset($this->fieldMappings[$fieldName])) {
            throw MappingException::mappingNotFound($this->name, $fieldName);
        }

        return $this->fieldMappings[$fieldName];
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifierFieldNames()
    {
        return [$this->identifier];
    }

    /**
     * {@inheritdoc}
     */
    public function getAssociationNames()
    {
        return array_keys($this->associationMappings);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeOfField($fieldName)
    {
        return isset($this->fieldMappings[$fieldName]) ?
                $this->fieldMappings[$fieldName]['type'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getNameOfField($fieldName)
    {
        return isset($this->fieldMappings[$fieldName]) ?
                $this->fieldMappings[$fieldName]['name'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getAssociationTargetClass($assocName)
    {
        if (!isset($this->associationMappings[$assocName])) {
            throw new InvalidArgumentException("Association name expected, '".$assocName."' is not an association.");
        }

        return $this->associationMappings[$assocName]['targetDocument'];
    }

    /**
     * {@inheritdoc}
     */
    public function isAssociationInverseSide($assocName)
    {
        throw new \Exception(__METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    public function getAssociationMappedByTargetField($assocName)
    {
        throw new \Exception(__METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifierValues($object)
    {
        return [$this->identifier => $this->reflFields[$this->identifier]->getValue($object)];
    }

    /**
     * Sets the change tracking policy used by this class.
     *
     * @param int $policy
     */
    public function setChangeTrackingPolicy($policy)
    {
        $this->changeTrackingPolicy = $policy;
    }

    /**
     * Whether the change tracking policy of this class is "deferred explicit".
     *
     * @return bool
     */
    public function isChangeTrackingDeferredExplicit()
    {
        return $this->changeTrackingPolicy == self::CHANGETRACKING_DEFERRED_EXPLICIT;
    }

    /**
     * Whether the change tracking policy of this class is "deferred implicit".
     *
     * @return bool
     */
    public function isChangeTrackingDeferredImplicit()
    {
        return $this->changeTrackingPolicy == self::CHANGETRACKING_DEFERRED_IMPLICIT;
    }

    /**
     * Whether the change tracking policy of this class is "notify".
     *
     * @return bool
     */
    public function isChangeTrackingNotify()
    {
        return $this->changeTrackingPolicy == self::CHANGETRACKING_NOTIFY;
    }

    /**
     * @return bool
     */
    public function isInheritanceTypeNone()
    {
        return $this->inheritanceType == self::INHERITANCE_TYPE_NONE;
    }

    /**
     * Validates lifecycle callbacks.
     *
     * @param \Doctrine\Common\Persistence\Mapping\ReflectionService $reflService
     *
     * @return void
     *
     * @throws MappingException
     */
    public function validateLifecycleCallbacks($reflService)
    {
        foreach ($this->lifecycleCallbacks as $callbacks) {
            foreach ($callbacks as $callbackFuncName) {
                if ( ! $reflService->hasPublicMethod($this->name, $callbackFuncName)) {
                    throw MappingException::lifecycleCallbackMethodNotFound($this->name, $callbackFuncName);
                }
            }
        }
    }

    /**
     * Whether the class has any attached lifecycle listeners or callbacks for a lifecycle event.
     *
     * @param string $lifecycleEvent
     *
     * @return boolean
     */
    public function hasLifecycleCallbacks($lifecycleEvent)
    {
        return isset($this->lifecycleCallbacks[$lifecycleEvent]);
    }

    /**
     * Gets the registered lifecycle callbacks for an event.
     *
     * @param string $event
     *
     * @return array
     */
    public function getLifecycleCallbacks($event)
    {
        return isset($this->lifecycleCallbacks[$event]) ? $this->lifecycleCallbacks[$event] : array();
    }

    /**
     * Adds a lifecycle callback for objects of this class.
     *
     * @param string $callback
     * @param string $event
     *
     * @return void
     */
    public function addLifecycleCallback($callback, $event)
    {
        if(isset($this->lifecycleCallbacks[$event]) && in_array($callback, $this->lifecycleCallbacks[$event])) {
            return;
        }

        $this->lifecycleCallbacks[$event][] = $callback;
    }

    /**
     * Sets the lifecycle callbacks for objects of this class.
     * Any previously registered callbacks are overwritten.
     *
     * @param array $callbacks
     *
     * @return void
     */
    public function setLifecycleCallbacks(array $callbacks)
    {
        $this->lifecycleCallbacks = $callbacks;
    }

    /**
     * Adds a entity listener for objects of this class.
     *
     * @param string $eventName The entity lifecycle event.
     * @param string $class     The listener class.
     * @param string $method    The listener callback method.
     *
     * @throws \Redking\ParseBundle\Mapping\MappingException
     */
    public function addObjectListener($eventName, $class, $method)
    {
        $class = $this->fullyQualifiedClassName($class);

        if ( ! class_exists($class)) {
            throw MappingException::objectListenerClassNotFound($class, $this->name);
        }

        if ( ! method_exists($class, $method)) {
            throw MappingException::objectListenerMethodNotFound($class, $method, $this->name);
        }

        $this->objectListeners[$eventName][] = array(
            'class'  => $class,
            'method' => $method
        );
    }

    /**
     * @param   string $className
     * @return  string
     */
    public function fullyQualifiedClassName($className)
    {
        if ($className !== null && strpos($className, '\\') === false && strlen($this->namespace) > 0) {
            return $this->namespace . '\\' . $className;
        }

        return $className;
    }
}
