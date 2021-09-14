<?php

namespace Redking\ParseBundle\Mapping\Driver;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\AnnotationDriver as AbstractAnnotationDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Redking\ParseBundle\Mapping\Annotations as ORM;
use Redking\ParseBundle\Mapping\MappingException;


/**
 * The AnnotationDriver reads the mapping metadata from docblock annotations.
 */
class AnnotationDriver extends AbstractAnnotationDriver implements MappingDriver
{
    protected $entityAnnotationClasses = array(
        'Redking\\ParseBundle\\Mapping\\Annotations\\ParseObject' => 1,
    );
    /**
     * Registers annotation classes to the common registry.
     *
     * This method should be called when bootstrapping your application.
     */
    public static function registerAnnotationClasses()
    {
        AnnotationRegistry::registerFile(__DIR__ . '/../Annotations/DoctrineAnnotations.php');
    }

    /**
     * {@inheritdoc}
     */
    public function loadMetadataForClass($className, ClassMetadata $class)
    {
        /** @var $class ClassMetadataInfo */
        $reflClass = $class->getReflectionClass();

        $classAnnotations = $this->reader->getClassAnnotations($reflClass);

        $objectAnnots = array();
        foreach ($classAnnotations as $annot) {
            $classAnnotations[get_class($annot)] = $annot;

            foreach ($this->entityAnnotationClasses as $annotClass => $i) {
                if ($annot instanceof $annotClass) {
                    $objectAnnots[$i] = $annot;
                    continue 2;
                }
            }

            // non-document class annotations
            if ($annot instanceof ORM\InheritanceType) {
                $class->setInheritanceType(constant(ClassMetadata::class . '::INHERITANCE_TYPE_'.strtoupper($annot->value)));
            }
        }

        if ( ! $objectAnnots) {
            throw MappingException::classIsNotAValidDocument($className);
        }

        // find the winning document annotation
        ksort($objectAnnots);
        $documentAnnot = reset($objectAnnots);

        if ($documentAnnot instanceof ORM\MappedSuperclass) {
            $class->isMappedSuperclass = true;
        }

        if (isset($documentAnnot->collection)) {
            $class->setCollection($documentAnnot->collection);
        }
        if (isset($documentAnnot->repositoryClass)) {
            $class->setCustomRepositoryClass($documentAnnot->repositoryClass);
        }

        foreach ($reflClass->getProperties() as $property) {
            if (($class->isMappedSuperclass && ! $property->isPrivate())
                ||
                ($class->isInheritedField($property->name) && $property->getDeclaringClass()->name !== $class->name)) {
                continue;
            }

            $indexes = array();
            $mapping = array('fieldName' => $property->getName());
            $fieldAnnot = null;

            foreach ($this->reader->getPropertyAnnotations($property) as $annot) {
                if ($annot instanceof ORM\AbstractField) {
                    $fieldAnnot = $annot;
                }
                if ($annot instanceof ORM\AbstractIndex) {
                    $indexes[] = $annot;
                }
                if ($annot instanceof ORM\Indexes) {
                    foreach (is_array($annot->value) ? $annot->value : array($annot->value) as $index) {
                        $indexes[] = $index;
                    }
                } elseif ($annot instanceof ORM\AlsoLoad) {
                    $mapping['alsoLoadFields'] = (array) $annot->value;
                } elseif ($annot instanceof ORM\Version) {
                    $mapping['version'] = true;
                } elseif ($annot instanceof ORM\Lock) {
                    $mapping['lock'] = true;
                }
            }

            if ($fieldAnnot) {
                $mapping = array_replace($mapping, (array) $fieldAnnot);
                $class->mapField($mapping);
            }

            if ($indexes) {
                foreach ($indexes as $index) {
                    $name = isset($mapping['name']) ? $mapping['name'] : $mapping['fieldName'];
                    $keys = array($name => $index->order ?: 'asc');
                    $this->addIndex($class, $index, $keys);
                }
            }
        }


        /** @var $method \ReflectionMethod */
        foreach ($reflClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            /* Filter for the declaring class only. Callbacks from parent
             * classes will already be registered.
             */
            if ($method->getDeclaringClass()->name !== $reflClass->name) {
                continue;
            }

            foreach ($this->reader->getMethodAnnotations($method) as $annot) {
                if ($annot instanceof ORM\AlsoLoad) {
                    $class->registerAlsoLoadMethod($method->getName(), $annot->value);
                }

                if ( ! isset($classAnnotations[ORM\HasLifecycleCallbacks::class])) {
                    continue;
                }

                if ($annot instanceof ORM\PrePersist) {
                    $class->addLifecycleCallback($method->getName(), Events::prePersist);
                } elseif ($annot instanceof ORM\PostPersist) {
                    $class->addLifecycleCallback($method->getName(), Events::postPersist);
                } elseif ($annot instanceof ORM\PreUpdate) {
                    $class->addLifecycleCallback($method->getName(), Events::preUpdate);
                } elseif ($annot instanceof ORM\PostUpdate) {
                    $class->addLifecycleCallback($method->getName(), Events::postUpdate);
                } elseif ($annot instanceof ORM\PreRemove) {
                    $class->addLifecycleCallback($method->getName(), Events::preRemove);
                } elseif ($annot instanceof ORM\PostRemove) {
                    $class->addLifecycleCallback($method->getName(), Events::postRemove);
                } elseif ($annot instanceof ORM\PreLoad) {
                    $class->addLifecycleCallback($method->getName(), Events::preLoad);
                } elseif ($annot instanceof ORM\PostLoad) {
                    $class->addLifecycleCallback($method->getName(), Events::postLoad);
                } elseif ($annot instanceof ORM\PreFlush) {
                    $class->addLifecycleCallback($method->getName(), Events::preFlush);
                }
            }
        }
    }

    /**
     * Factory method for the Annotation Driver
     *
     * @param array|string $paths
     * @param Reader $reader
     * @return AnnotationDriver
     */
    public static function create($paths = array(), Reader $reader = null)
    {
        if ($reader === null) {
            $reader = new AnnotationReader();
        }
        self::registerAnnotationClasses();

        return new self($reader, $paths);
    }
}