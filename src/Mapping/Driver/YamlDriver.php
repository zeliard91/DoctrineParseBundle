<?php

namespace Redking\ParseBundle\Mapping\Driver;

use Redking\ParseBundle\Mapping\ClassMetadata as ParseClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\FileDriver;
use Doctrine\Persistence\Mapping\Driver\SymfonyFileLocator;
use Symfony\Component\Yaml\Yaml;
use Redking\ParseBundle\Mapping\Builder\ObjectListenerBuilder;
use Redking\ParseBundle\Mapping\MappingException;

/**
 * The YamlDriver reads the mapping metadata from yaml schema files.
 *
 * @since       1.0
 *
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 * @author      Roman Borschel <roman@code-factory.org>
 */
class YamlDriver extends FileDriver
{
    const DEFAULT_FILE_EXTENSION = '.parse.yml';

    /**
     * {@inheritdoc}
     */
    public function __construct($prefixes, $fileExtension = self::DEFAULT_FILE_EXTENSION)
    {
        $locator = new SymfonyFileLocator((array) $prefixes, $fileExtension);
        parent::__construct($locator, $fileExtension);
    }

    /**
     * {@inheritdoc}
     */
    public function loadMetadataForClass($className, ClassMetadata $class): void
    {
        /* @var $class ClassMetadata */
        $element = $this->getElement($className);
        if (!$element) {
            return;
        }
        $element['type'] = isset($element['type']) ? $element['type'] : 'document';

        if (isset($element['collection'])) {
            $class->setCollection($element['collection']);
        }
        if ($element['type'] == 'document') {
            if (isset($element['repositoryClass'])) {
                $class->setCustomRepositoryClass($element['repositoryClass']);
            }
        } elseif ($element['type'] === 'mappedSuperclass') {
            $class->setCustomRepositoryClass(
                isset($element['repositoryClass']) ? $element['repositoryClass'] : null
            );
            $class->isMappedSuperclass = true;
        } elseif ($element['type'] === 'embeddedDocument') {
            $class->isEmbeddedDocument = true;
        }
        if (isset($element['indexes'])) {
            foreach ($element['indexes'] as $index) {
                $class->addIndex($index['keys'], isset($index['options']) ? $index['options'] : array());
            }
        }
        if (isset($element['inheritanceType'])) {
            $class->setInheritanceType(constant('Redking\ParseBundle\Mapping\ClassMetadata::INHERITANCE_TYPE_'.strtoupper($element['inheritanceType'])));
        }
        if (isset($element['discriminatorField'])) {
            $class->setDiscriminatorField($this->parseDiscriminatorField($element['discriminatorField']));
        }
        if (isset($element['discriminatorMap'])) {
            $class->setDiscriminatorMap($element['discriminatorMap']);
        }
        if (isset($element['defaultDiscriminatorValue'])) {
            $class->setDefaultDiscriminatorValue($element['defaultDiscriminatorValue']);
        }
        if (isset($element['changeTrackingPolicy'])) {
            $class->setChangeTrackingPolicy(constant('Redking\ParseBundle\Mapping\ClassMetadata::CHANGETRACKING_'
                    .strtoupper($element['changeTrackingPolicy'])));
        }
        if (isset($element['requireIndexes'])) {
            $class->setRequireIndexes($element['requireIndexes']);
        }
        if (isset($element['slaveOkay'])) {
            $class->setSlaveOkay($element['slaveOkay']);
        }

        $this->addMandatoryFieldMapping($class);

        if (isset($element['fields'])) {
            foreach ($element['fields'] as $fieldName => $mapping) {
                if (is_string($mapping)) {
                    $type = $mapping;
                    $mapping = array();
                    $mapping['type'] = $type;
                }
                if (!isset($mapping['fieldName'])) {
                    $mapping['fieldName'] = $fieldName;
                }
                if (isset($mapping['type']) && !empty($mapping['embedded'])) {
                    $this->addMappingFromEmbed($class, $fieldName, $mapping, $mapping['type']);
                } elseif (isset($mapping['type']) && !empty($mapping['reference'])) {
                    $this->addMappingFromReference($class, $fieldName, $mapping, $mapping['type']);
                } else {
                    $this->addFieldMapping($class, $mapping);
                }
            }
        }
        if (isset($element['referenceOne'])) {
            foreach ($element['referenceOne'] as $fieldName => $reference) {
                $this->addMappingFromReference($class, $fieldName, $reference, 'one');
            }
        }
        if (isset($element['referenceMany'])) {
            foreach ($element['referenceMany'] as $fieldName => $reference) {
                $this->addMappingFromReference($class, $fieldName, $reference, 'many');
            }
        }
        if (isset($element['alsoLoadMethods'])) {
            foreach ($element['alsoLoadMethods'] as $methodName => $fieldName) {
                $class->registerAlsoLoadMethod($methodName, $fieldName);
            }
        }

        // Evaluate lifeCycleCallbacks
        if (isset($element['lifecycleCallbacks'])) {
            foreach ($element['lifecycleCallbacks'] as $type => $methods) {
                foreach ($methods as $method) {
                    $class->addLifecycleCallback($method, constant('Redking\ParseBundle\Events::' . $type));
                }
            }
        }

        // Evaluate objectListeners
        if (isset($element['objectListeners'])) {
            foreach ($element['objectListeners'] as $className => $objectListener) {
                // Evaluate the listener using naming convention.
                if (empty($objectListener)) {
                    ObjectListenerBuilder::bindObjectListener($class, $className);

                    continue;
                }

                foreach ($objectListener as $eventName => $callbackElement){
                    foreach ($callbackElement as $methodName){
                        $class->addObjectListener($eventName, $className, $methodName);
                    }
                }
            }
        }
    }

    private function addMandatoryFieldMapping(ClassMetadata $class)
    {
        $mappings = [
            'id' => [
                'id' => true,
                'type' => 'string',
                'fieldName' => 'id',
            ],
            'createdAt' => [
                'type' => 'DateTime',
                'fieldName' => 'createdAt',
            ],

            'updatedAt' => [
                'type' => 'DateTime',
                'fieldName' => 'updatedAt',
            ],
        ];

        foreach ($mappings as $mapping) {
            $class->mapField($mapping);
        }
    }

    private function addFieldMapping(ClassMetadata $class, $mapping)
    {
        if (isset($mapping['name'])) {
            $name = $mapping['name'];
        } elseif (isset($mapping['fieldName'])) {
            $name = $mapping['fieldName'];
        } else {
            throw new \InvalidArgumentException('Cannot infer a MongoDB name from the mapping');
        }

        /* Parse only stores the MongoDB key specification of an index, so these have to
         * fail loudly instead of leaving the impression that the index was created.
         */
        foreach (array('unique', 'sparse') as $unsupported) {
            if (isset($mapping[$unsupported])) {
                throw MappingException::unsupportedIndexOption($class->name, $unsupported);
            }
        }

        /* The index declaration is not part of the field mapping itself. */
        $index = isset($mapping['index']) ? $mapping['index'] : null;
        unset($mapping['index']);

        $class->mapField($mapping);

        if ($index === null) {
            return;
        }

        /* Along the field name, the only accepted options are the order and the name. */
        $keys = array($name => 'asc');
        $options = array();

        if (is_array($index)) {
            $options = $index;

            if (isset($options['order'])) {
                $keys[$name] = $options['order'];
                unset($options['order']);
            }

            foreach (array_keys($options) as $option) {
                if ($option !== 'name') {
                    throw MappingException::unsupportedIndexOption($class->name, (string) $option);
                }
            }
        }

        $class->addIndex($keys, $options);
    }

    private function addMappingFromReference(ClassMetadata $class, $fieldName, $reference, $type)
    {
        $mapping = array(
            'cascade' => isset($reference['cascade']) ? $reference['cascade'] : null,
            'orphanRemoval' => isset($reference['orphanRemoval']) ? $reference['orphanRemoval'] : false,
            'type' => $type,
            'reference' => true,
            'simple' => isset($reference['simple']) ? (boolean) $reference['simple'] : false,
            'targetDocument' => isset($reference['targetDocument']) ? $reference['targetDocument'] : null,
            'fieldName' => $fieldName,
            'inversedBy' => isset($reference['inversedBy']) ? (string) $reference['inversedBy'] : null,
            'mappedBy' => isset($reference['mappedBy']) ? (string) $reference['mappedBy'] : null,
            'repositoryMethod' => isset($reference['repositoryMethod']) ? (string) $reference['repositoryMethod'] : null,
            'limit' => isset($reference['limit']) ? (integer) $reference['limit'] : null,
            'skip' => isset($reference['skip']) ? (integer) $reference['skip'] : null,
            'discriminatorField' => isset($reference['discriminatorField']) ? $this->parseDiscriminatorField($reference['discriminatorField']) : null,
            'discriminatorMap' => isset($reference['discriminatorMap']) ? $reference['discriminatorMap'] : null,
            'defaultDiscriminatorValue' => isset($reference['defaultDiscriminatorValue']) ? $reference['defaultDiscriminatorValue'] : null,
            'sort' => isset($reference['sort']) ? $reference['sort'] : [],
            'criteria' => isset($reference['criteria']) ? $reference['criteria'] : [],
        );
        if (isset($reference['name'])) {
            $mapping['name'] = $reference['name'];
        }

        if (isset($reference['fetch'])) {
            $mapping['fetch'] = constant('Redking\ParseBundle\Mapping\ClassMetadata::FETCH_' . $reference['fetch']);
        }

        if ($type === ParseClassMetadata::MANY) {
            $mapping['implementation'] = isset($mapping['implementation']) ? constant('Redking\ParseBundle\Mapping\ClassMetadata::ASSOCIATION_IMPL_' . strtoupper($reference['implementation'])) : ParseClassMetadata::ASSOCIATION_IMPL_ARRAY;
            $mapping['lazyLoad'] = isset($reference['lazyLoad']) ? $reference['lazyLoad'] : true;
            $mapping['includeKeys'] = isset($reference['includeKeys']) ? $reference['includeKeys'] : null;
        }
        $this->addFieldMapping($class, $mapping);
    }

    /**
     * Parses the class or field-level "discriminatorField" option.
     *
     * If the value is an array, check the "name" option before falling back to
     * the deprecated "fieldName" option (for BC). Otherwise, the value must be
     * a string.
     *
     * @param array|string $discriminatorField
     *
     * @return string
     *
     * @throws \InvalidArgumentException if the value is neither a string nor an
     *                                   array with a "name" or "fieldName" key.
     */
    private function parseDiscriminatorField($discriminatorField)
    {
        if (is_string($discriminatorField)) {
            return $discriminatorField;
        }

        if (!is_array($discriminatorField)) {
            throw new \InvalidArgumentException('Expected array or string for discriminatorField; found: '.gettype($discriminatorField));
        }

        if (isset($discriminatorField['name'])) {
            return (string) $discriminatorField['name'];
        }

        if (isset($discriminatorField['fieldName'])) {
            return (string) $discriminatorField['fieldName'];
        }

        throw new \InvalidArgumentException('Expected "name" or "fieldName" key in discriminatorField array; found neither.');
    }

    /**
     * {@inheritdoc}
     */
    protected function loadMappingFile($file): array
    {
        return Yaml::parse(file_get_contents($file));
    }
}
