<?php

namespace Redking\ParseBundle\Mapping;

use Doctrine\Common\Persistence\Mapping\AbstractClassMetadataFactory;
use Doctrine\Common\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;
use Doctrine\Common\Persistence\Mapping\ReflectionService;
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
     * @param ObjectManager $om
     */
    public function setObjectManager(ObjectManager $om)
    {
        $this->om = $om;
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize()
    {
        $this->driver = $this->om->getConfiguration()->getMetadataDriverImpl();
        $this->evm = $this->om->getEventManager();
        $this->initialized = true;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFqcnFromAlias($namespaceAlias, $simpleClassName)
    {
        return $this->om->getConfiguration()->getEntityNamespace($namespaceAlias).'\\'.$simpleClassName;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDriver()
    {
        return $this->driver;
    }

    /**
     * {@inheritdoc}
     */
    protected function wakeupReflection(ClassMetadataInterface $class, ReflectionService $reflService)
    {
        /* @var $class ClassMetadata */
        $class->wakeupReflection($reflService);
    }

    /**
     * {@inheritdoc}
     */
    protected function initializeReflection(ClassMetadataInterface $class, ReflectionService $reflService)
    {
        /* @var $class ClassMetadata */
        $class->initializeReflection($reflService);
    }

    /**
     * {@inheritdoc}
     */
    protected function isEntity(ClassMetadataInterface $class)
    {
        return isset($class->isMappedSuperclass) && $class->isMappedSuperclass === false;
    }

    /**
     * {@inheritdoc}
     */
    protected function doLoadMetadata($class, $parent, $rootEntityFound, array $nonSuperclassParents)
    {
        /* @var $class ClassMetadata */
        /** @var $parent ClassMetadata */
        if ($parent) {
            $class->setInheritanceType($parent->inheritanceType);
            $class->setDiscriminatorField($parent->discriminatorField);
            $class->setDiscriminatorMap($parent->discriminatorMap);
            $class->setDefaultDiscriminatorValue($parent->defaultDiscriminatorValue);
            $class->setIdGeneratorType($parent->generatorType);
            $this->addInheritedFields($class, $parent);
            $this->addInheritedRelations($class, $parent);
            $this->addInheritedIndexes($class, $parent);
            $class->setIdentifier($parent->identifier);
            $class->setVersioned($parent->isVersioned);
            $class->setVersionField($parent->versionField);
            $class->setLifecycleCallbacks($parent->lifecycleCallbacks);
            $class->setAlsoLoadMethods($parent->alsoLoadMethods);
            $class->setChangeTrackingPolicy($parent->changeTrackingPolicy);
            $class->setFile($parent->getFile());
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
    protected function newClassMetadataInstance($className)
    {
        return new ClassMetadata($className, $this->om->getConfiguration()->getNamingStrategy());
    }
}
