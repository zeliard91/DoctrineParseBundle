<?php

namespace Redking\ParseBundle\Mapping;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\Common\EventManager;
use Doctrine\Persistence\Mapping\AbstractClassMetadataFactory;
use Doctrine\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\ReflectionService;
use Redking\ParseBundle\ObjectManager;
use Redking\ParseBundle\Event\LoadClassMetadataEventArgs;
use Redking\ParseBundle\Events;

/**
 * Contract for a Doctrine persistence layer ClassMetadata class to implement.
 */
class ClassMetadataFactory extends AbstractClassMetadataFactory
{
    protected $cacheSalt = '$PARSECLASSMETADATA';

    /**
     * @var ObjectManager
     */
    private $om;

    /**
     * @var MappingDriver
     */
    private $driver;

    /**
     * @var EventManager
     */
    private $evm;

    /**
     * @param ObjectManager $om
     */
    public function setObjectManager(ObjectManager $om)
    {
        $this->om = $om;
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(): void
    {
        $this->driver = $this->om->getConfiguration()->getMetadataDriverImpl();
        $this->evm = $this->om->getEventManager();
        $this->initialized = true;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFqcnFromAlias($namespaceAlias, $simpleClassName): string
    {
        return $this->om->getConfiguration()->getEntityNamespace($namespaceAlias).'\\'.$simpleClassName;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDriver(): MappingDriver
    {
        return $this->driver;
    }

    /**
     * {@inheritdoc}
     */
    protected function wakeupReflection(ClassMetadataInterface $class, ReflectionService $reflService): void
    {
        /* @var $class ClassMetadata */
        $class->wakeupReflection($reflService);
    }

    /**
     * {@inheritdoc}
     */
    protected function initializeReflection(ClassMetadataInterface $class, ReflectionService $reflService): void
    {
        /* @var $class ClassMetadata */
        $class->initializeReflection($reflService);
    }

    /**
     * {@inheritdoc}
     */
    protected function isEntity(ClassMetadataInterface $class): bool
    {
        return isset($class->isMappedSuperclass) && $class->isMappedSuperclass === false;
    }

    /**
     * {@inheritdoc}
     */
    protected function doLoadMetadata($class, $parent, $rootEntityFound, array $nonSuperclassParents): void
    {
        /* @var $class ClassMetadata */
        /** @var $parent ClassMetadata */
        if ($parent && $parent instanceof ClassMetadata) {
            $class->setInheritanceType($parent->inheritanceType);
            $class->setDiscriminatorField($parent->discriminatorField);
            $class->setDiscriminatorMap($parent->discriminatorMap);
            $class->setDefaultDiscriminatorValue($parent->defaultDiscriminatorValue);
            $this->addInheritedFields($class, $parent);
            $this->addInheritedRelations($class, $parent);
            $class->setIdentifier($parent->identifier);
            $class->setLifecycleCallbacks($parent->lifecycleCallbacks);
            $class->setChangeTrackingPolicy($parent->changeTrackingPolicy);

            if ($parent->isMappedSuperclass) {
                $class->setCustomRepositoryClass($parent->customRepositoryClassName);
            }
        }

        // Invoke driver
        try {
            $this->driver->loadMetadataForClass($class->getName(), $class);
        } catch (\ReflectionException $e) {
            throw MappingException::reflectionFailure($class->getName(), $e);
        }

        $this->validateIdentifier($class);
        $class->validateLifecycleCallbacks($this->getReflectionService());

        if ($parent && $parent->isInheritanceTypeSingleCollection()) {
            $class->setDatabase($parent->getDatabase());
            $class->setCollection($parent->getCollection());
        }

        $class->setParentClasses($nonSuperclassParents);

        if ($this->evm->hasListeners(Events::loadClassMetadata)) {
            $eventArgs = new LoadClassMetadataEventArgs($class, $this->om);
            $this->evm->dispatchEvent(Events::loadClassMetadata, $eventArgs);
        }
    }

    /**
     * Validates the identifier mapping.
     *
     * @param ClassMetadata $class
     *
     * @throws MappingException
     */
    protected function validateIdentifier($class)
    {
        if (!$class->identifier && !$class->isMappedSuperclass && !$class->isEmbeddedDocument) {
            throw MappingException::identifierRequired($class->name);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function newClassMetadataInstance($className): ClassMetadata
    {
        return new ClassMetadata($className, $this->om->getConfiguration()->getNamingStrategy());
    }

    /**
     * Adds inherited fields to the subclass mapping.
     *
     * @param ClassMetadata $subClass
     * @param ClassMetadata $parentClass
     */
    private function addInheritedFields(ClassMetadata $subClass, ClassMetadata $parentClass)
    {
        foreach ($parentClass->fieldMappings as $fieldName => $mapping) {
            if ( ! isset($mapping['inherited']) && ! $parentClass->isMappedSuperclass) {
                $mapping['inherited'] = $parentClass->name;
            }
            if ( ! isset($mapping['declared'])) {
                $mapping['declared'] = $parentClass->name;
            }
            $subClass->addInheritedFieldMapping($mapping);
        }
        foreach ($parentClass->reflFields as $name => $field) {
            $subClass->reflFields[$name] = $field;
        }
    }


    /**
     * Adds inherited association mappings to the subclass mapping.
     *
     * @param \Doctrine\ORM\Mapping\ClassMetadata $subClass
     * @param \Doctrine\ORM\Mapping\ClassMetadata $parentClass
     *
     * @return void
     *
     * @throws MappingException
     */
    private function addInheritedRelations(ClassMetadata $subClass, ClassMetadata $parentClass)
    {
        foreach ($parentClass->associationMappings as $field => $mapping) {
            if ($parentClass->isMappedSuperclass) {
                $mapping['sourceDocument'] = $subClass->name;
            }

            if ( ! isset($mapping['inherited']) && ! $parentClass->isMappedSuperclass) {
                $mapping['inherited'] = $parentClass->name;
            }
            if ( ! isset($mapping['declared'])) {
                $mapping['declared'] = $parentClass->name;
            }
            $subClass->addInheritedAssociationMapping($mapping);
        }
    }

    public function getCacheDriver(): Cache
    {
        return DoctrineProvider::wrap($this->getCache());
    }
}
