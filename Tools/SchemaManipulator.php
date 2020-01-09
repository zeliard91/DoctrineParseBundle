<?php

namespace Redking\ParseBundle\Tools;

use Parse\ParseSchema;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\ObjectManager;

class SchemaManipulator
{
    /**
     * @var \Redking\ParseBundle\ObjectManager
     */
    private $om;

    /**
     * Keep a class schema in cache.
     *
     * @var array
     */
    private $cacheSchema = [];

    /**
     * Array representation of the global schema.
     *
     * @var array
     */
    private $classesSchema;

    /**
     * Constructor.
     *
     * @param ObjectManager $om A ObjectManager instance
     */
    public function __construct(ObjectManager $om)
    {
        $this->om = $om;
    }

    /**
     * Synchronize Schema with the metadata.
     *
     * @return void
     */
    public function sync()
    {
        $this->syncIndexes();
    }

    /**
     * Make sure the indexes are created.
     *
     * @return void
     */
    private function syncIndexes()
    {
        $classes = $this->om->getMetadataFactory()->getAllMetadata();

        foreach ($classes as $class) {
            if (empty($class->getCollection())) {
                continue;
            }

            $parseSchema = null;
            $schemaNeedsUpdate = false;

            // Auto create indexes on pointer relations.
            foreach ($class->getAssociationNames() as $assocName) {
                if ($class->isSingleValuedAssociation($assocName) 
                    && $this->hasField($class, $assocName) 
                    && !$this->hasIndex($class, $assocName)) {

                    if (null === $parseSchema) {
                        $parseSchema = $this->getSchemaForCollection($class->getCollection());
                    }
                    $this->addIndex($class, $assocName, $parseSchema);
                    $schemaNeedsUpdate = true;
                }
            }

            if ($schemaNeedsUpdate) {
                $parseSchema->update();
            }
        }
    }

    public function addIndex(ClassMetadata $class, $fields, ParseSchema $parseSchema = null, string $name = null)
    {
        if (is_scalar($fields)) {
            $fields = [$fields];
        }

        $idxName = 'idx';
        $params = [];
        foreach ($fields as $field) {
            $parseFieldName = $class->getNameOfField($field);
            if ($class->isSingleValuedAssociation($field)) {
                $parseFieldName = '_p_'.$parseFieldName;
            }
            $idxName .= '_'.$parseFieldName;
            $params[$parseFieldName] = 1;
        }

        if (null !== $name) {
            $idxName = $name;
        }

        if (null === $parseSchema) {
            $parseSchema = $this->getSchemaForCollection($class->getCollection());
        }

        $parseSchema->addIndex($idxName, $params);
    }

    /**
     * Search if a field is present in an index.
     *
     * @param  ClassMetadata $class
     * @param  string        $fieldName
     * @return boolean
     */
    public function hasIndex(ClassMetadata $class, string $fieldName)
    {
        $schema = $this->getSchemaForClassMetadataFromAll($class);
        if (empty($schema)) {
            return false;
        }
        if (!isset($schema['indexes'])) {
            return false;
        }

        $parseFieldName = $class->getNameOfField($fieldName);
        foreach ($schema['indexes'] as $name => $index) {
            foreach ($index as $_fieldName => $value) {
                if ($_fieldName === $parseFieldName) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Search if a field exists in the schema
     *
     * @param  ClassMetadata $class
     * @param  string        $fieldName
     * @return boolean
     */
    public function hasField(ClassMetadata $class, string $fieldName)
    {
        $schema = $this->getSchemaForClassMetadataFromAll($class);
        if (empty($schema)) {
            return false;
        }

        $parseFieldName = $class->getNameOfField($fieldName);

        return isset($schema['fields'][$parseFieldName]);
    }

    /**
     * @param  boolean $reload
     * @return array
     */
    private function getAllSchema($reload = false)
    {
        if (null === $this->classesSchema || $reload) {
            $schema = new ParseSchema();
            $this->classesSchema = $schema->all();
        }

        return $this->classesSchema;
    }

    /**
     * @param  string $collection
     * @return array
     */
    private function getSchemaForCollectionFromAll(string $collection)
    {
        foreach ($this->getAllSchema() as $schema) {
            if ($collection === $schema['className']) {
                return $schema;
            }
        }

        return [];
    }

    /**
     * @param  string $collection
     * @return array
     */
    private function getSchemaForClassMetadataFromAll(ClassMetadata $class)
    {
        $collection = $class->getCollection();
        if (empty($collection)) {
            return [];
        }

        return $this->getSchemaForCollectionFromAll($collection);
    }

    /**
     * Returns a ParseSchema for a PHP Class.
     *
     * @param  string $class
     * @return \Parse\ParseSchema
     */
    private function getSchemaForClass(string $class): ParseSchema
    {
        return $this->getSchemaForCollection($this->om->getMetadataFactory()->getMetadataFor($class)->getCollection());
    }

    /**
     * Returns a ParseSchema for a collection.
     *
     * @param  string $collectionName
     * @return \Parse\ParseSchema
     */
    private function getSchemaForCollection(string $collectionName): ParseSchema
    {
        if (!isset($this->cacheSchema[$collectionName])) {
            $this->cacheSchema[$collectionName] = new ParseSchema($collectionName);
        }

        return $this->cacheSchema[$collectionName];
    }

    /**
     * @param  ClassMetadata $class
     * @param  string        $fieldName
     * @return string
     */
    private function getIndexNameForField(ClassMetadata $class, string $fieldName)
    {
        return 'idx_'.$class->getNameOfField($fieldName);
    }
}
