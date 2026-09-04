<?php

declare(strict_types=1);

namespace Redking\ParseBundle\Mapping;

use Redking\ParseBundle\Types\Type;

/**
 * Translates the index and field declarations of a ClassMetadata into what the
 * Parse schema API expects.
 *
 * Parse stores nothing but the MongoDB key specification of an index and does not
 * transform the keys, so the column names have to be the MongoDB ones: a Pointer
 * column is stored as "_p_<name>".
 */
final class IndexMapper
{
    /**
     * Maximum length of a MongoDB index name.
     */
    private const MAX_INDEX_NAME_LENGTH = 127;

    /**
     * Parse columns that every class owns and that can not be indexed nor submitted
     * as a field.
     */
    private const DEFAULT_COLUMNS = ['objectId', 'createdAt', 'updatedAt', 'ACL'];

    /**
     * Maps a bundle type to a Parse schema type.
     */
    private const TYPE_MAP = [
        Type::BOOLEAN => 'Boolean',
        Type::INTEGER => 'Number',
        Type::FLOAT => 'Number',
        Type::STRING => 'String',
        Type::ENCRYPTED_STRING => 'String',
        Type::DATE => 'Date',
        Type::TARRAY => 'Array',
        Type::TOBJECT => 'Object',
        Type::HASH => 'Object',
        Type::FILE => 'File',
        Type::GEOPOINT => 'GeoPoint',
    ];

    /**
     * Returns the indexes of a class, keyed by index name, as MongoDB key specifications.
     *
     * @return array<string, array<string, int|string>>
     *
     * @throws MappingException
     */
    public function getParseIndexes(ClassMetadata $class): array
    {
        $indexes = [];

        foreach ($class->getIndexes() as $index) {
            $spec = [];

            foreach ($index['keys'] as $key => $order) {
                $spec[$this->resolveKey($class, (string) $key, $order)] = $order;
            }

            $name = $index['options']['name'] ?? $this->generateIndexName($spec);

            if (strlen($name) > self::MAX_INDEX_NAME_LENGTH) {
                throw MappingException::indexNameTooLong($class->name, $name);
            }

            if (isset($indexes[$name])) {
                if ($indexes[$name] !== $spec) {
                    throw MappingException::duplicateIndexName($class->name, $name);
                }

                // Same name and same keys: the index was declared twice, typically
                // inherited from a mapped superclass. Keep a single copy.
                continue;
            }

            $indexes[$name] = $spec;
        }

        return $indexes;
    }

    /**
     * Returns the fields of a class that the Parse schema is able to hold, keyed by
     * Parse column name.
     *
     * The default columns (objectId, createdAt, updatedAt, ACL), the inverse sides of
     * associations and the references without a target document are left out.
     *
     * @return array<string, array{type: string, targetDocument?: string}>
     */
    public function getParseFields(ClassMetadata $class): array
    {
        $fields = [];

        foreach ($class->fieldMappings as $fieldName => $mapping) {
            if ($fieldName === $class->identifier || in_array($mapping['name'], self::DEFAULT_COLUMNS, true)) {
                continue;
            }

            if (!empty($mapping['isInverseSide'])) {
                continue;
            }

            if (isset($mapping['association'])) {
                $definition = $this->associationFieldDefinition($mapping);

                if ($definition === null) {
                    continue;
                }

                $fields[$mapping['name']] = $definition;

                continue;
            }

            if (!isset(self::TYPE_MAP[$mapping['type']])) {
                continue;
            }

            $fields[$mapping['name']] = ['type' => self::TYPE_MAP[$mapping['type']]];
        }

        return $fields;
    }

    /**
     * Resolves a mapping key to the MongoDB column name it is stored under.
     *
     * @param string|int $order
     *
     * @throws MappingException
     */
    public function resolveKey(ClassMetadata $class, string $key, $order): string
    {
        $fieldName = $class->hasField($key) ? $key : $class->getFieldNameOfName($key);

        if ($fieldName === null) {
            throw MappingException::indexFieldNotMapped($class->name, $key);
        }

        if ($fieldName === $class->identifier) {
            throw MappingException::cannotIndexIdentifier($class->name, $fieldName);
        }

        $mapping = $class->fieldMappings[$fieldName];

        if (in_array($mapping['name'], ['createdAt', 'updatedAt'], true)) {
            throw MappingException::cannotIndexTimestampField($class->name, $fieldName);
        }

        if (($mapping['type'] ?? null) === Type::GEOPOINT && $order !== '2dsphere') {
            throw MappingException::invalidIndexOrder($class->name, $fieldName, $order);
        }

        if (!isset($mapping['association'])) {
            return $mapping['name'];
        }

        if (!empty($mapping['isInverseSide'])) {
            throw MappingException::cannotIndexInverseSideReference($class->name, $fieldName);
        }

        if ($mapping['association'] === ClassMetadata::REFERENCE_ONE) {
            // A Parse Pointer is stored in MongoDB under a "_p_" prefixed column.
            return '_p_'.$mapping['name'];
        }

        if ($this->referenceManyImplementation($mapping) === ClassMetadata::ASSOCIATION_IMPL_RELATION) {
            throw MappingException::cannotIndexRelation($class->name, $fieldName);
        }

        // Array of pointers: stored as a plain array under the field name.
        return $mapping['name'];
    }

    /**
     * @param array<string, mixed> $mapping
     *
     * @return array{type: string, targetDocument?: string}|null
     */
    private function associationFieldDefinition(array $mapping): ?array
    {
        if (empty($mapping['targetDocument'])) {
            // Discriminated reference without a single target: Parse can not type it.
            return null;
        }

        if ($mapping['association'] === ClassMetadata::REFERENCE_ONE) {
            return ['type' => 'Pointer', 'targetDocument' => $mapping['targetDocument']];
        }

        if ($this->referenceManyImplementation($mapping) === ClassMetadata::ASSOCIATION_IMPL_RELATION) {
            return ['type' => 'Relation', 'targetDocument' => $mapping['targetDocument']];
        }

        return ['type' => 'Array'];
    }

    /**
     * The implementation may be missing from a YAML mapping, in which case an array of
     * pointers is used.
     *
     * @param array<string, mixed> $mapping
     */
    private function referenceManyImplementation(array $mapping): string
    {
        return $mapping['implementation'] ?? ClassMetadata::ASSOCIATION_IMPL_ARRAY;
    }

    /**
     * Builds the name MongoDB would give to that key specification, so that an index
     * created by hand is recognized as the mapped one instead of being duplicated.
     *
     * @param array<string, int|string> $spec
     */
    private function generateIndexName(array $spec): string
    {
        $parts = [];

        foreach ($spec as $column => $order) {
            $parts[] = $column.'_'.$order;
        }

        return implode('_', $parts);
    }
}
