<?php

namespace Redking\ParseBundle;

use InvalidArgumentException;
use Parse\ParseException;
use Parse\ParseSchema;
use Redking\ParseBundle\Exception\RedkingParseException;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\Mapping\ClassMetadataFactory;
use Redking\ParseBundle\Mapping\IndexMapper;

class SchemaManager
{
    /**
     * Parse error code returned when a class does not exist yet.
     */
    private const ERROR_INVALID_CLASS_NAME = 103;

    /**
     * Parse error code returned when the server runs with lockSchemas enabled.
     */
    private const ERROR_OPERATION_FORBIDDEN = 119;

    /**
     * Indexes Parse creates on its own, which must never be dropped nor compared
     * against the mapping.
     *
     * @var array<string, string[]>
     */
    private const PROTECTED_INDEXES = [
        '_User' => ['username_1', 'email_1', 'case_insensitive_username', 'case_insensitive_email'],
        '_Role' => ['name_1'],
    ];

    /**
     * @var ObjectManager
     */
    protected $om;

    /**
     * @var ClassMetadataFactory
     */
    protected $cmf;

    /**
     * @var IndexMapper
     */
    protected $indexMapper;

    public function __construct(ObjectManager $om, ClassMetadataFactory $cmf)
    {
        $this->om = $om;
        $this->cmf = $cmf;
        $this->indexMapper = new IndexMapper();
    }

    public function dropCollections(): void
    {
        foreach ($this->cmf->getAllMetadata() as $class) {
            assert($class instanceof ClassMetadata);
            if ($class->isMappedSuperclass) {
                continue;
            }

            $this->dropCollection($class->name);
        }
    }

    public function dropCollection(string $className): void
    {
        $class = $this->om->getClassMetadata($className);
        if ($class->isMappedSuperclass) {
            throw new InvalidArgumentException('Cannot delete mapped super class');
        }

        $schema = new ParseSchema($class->getCollection());
        $schema->purge();
    }

    /**
     * Returns the indexes declared in the mapping, keyed by index name.
     *
     * @return array<string, array<string, int|string>>
     */
    public function getObjectIndexes(string $className): array
    {
        return $this->indexMapper->getParseIndexes($this->om->getClassMetadata($className));
    }

    /**
     * Returns the indexes Parse currently holds for a class, without the ones that are
     * never managed by the bundle. An empty array is returned when the class does not
     * exist yet.
     *
     * @return array<string, array<string, int|string>>
     */
    public function getExistingObjectIndexes(string $className): array
    {
        $class = $this->om->getClassMetadata($className);

        $this->assertMasterKey();

        $remote = $this->fetchSchema($class);

        if ($remote === null) {
            return [];
        }

        return $this->filterManagedIndexes($class, $remote['indexes'] ?? []);
    }

    /**
     * Creates the missing indexes of every mapped class. Nothing is ever dropped:
     * indexes whose keys changed are reported as mismatched.
     *
     * @return array<string, array<string, mixed>>
     */
    public function ensureIndexes(bool $createFields = true, bool $dryRun = false): array
    {
        return $this->eachClass(fn (string $className) => $this->ensureObjectIndexes($className, $createFields, $dryRun));
    }

    /**
     * @return array<string, mixed>
     */
    public function ensureObjectIndexes(string $className, bool $createFields = true, bool $dryRun = false): array
    {
        $diff = $this->diff($className, $createFields);

        if ($diff['skipped'] !== null || ($diff['create'] === [] && $diff['fields'] === [])) {
            return $this->report($diff);
        }

        $this->write($diff['class'], $diff['schema'], $diff['exists'], $diff['fields'], [], $diff['create'], $dryRun);

        return $this->report($diff);
    }

    /**
     * Creates the missing indexes and recreates the ones whose keys changed.
     *
     * @return array<string, array<string, mixed>>
     */
    public function updateIndexes(bool $createFields = true, bool $dryRun = false): array
    {
        return $this->eachClass(fn (string $className) => $this->updateObjectIndexes($className, $createFields, $dryRun));
    }

    /**
     * @return array<string, mixed>
     */
    public function updateObjectIndexes(string $className, bool $createFields = true, bool $dryRun = false): array
    {
        $diff = $this->diff($className, $createFields);

        if ($diff['skipped'] !== null) {
            return $this->report($diff);
        }

        // A mismatched index has to be dropped before being created again.
        foreach ($diff['mismatched'] as $name => $change) {
            $diff['dropped'][] = $name;
            $diff['create'][$name] = $change['to'];
        }
        $diff['mismatched'] = [];

        if ($diff['create'] === [] && $diff['dropped'] === [] && $diff['fields'] === []) {
            return $this->report($diff);
        }

        $this->write($diff['class'], $diff['schema'], $diff['exists'], $diff['fields'], $diff['dropped'], $diff['create'], $dryRun);

        return $this->report($diff);
    }

    /**
     * Drops the indexes declared in the mapping. Indexes that are not mapped are never
     * touched.
     *
     * @return array<string, array<string, mixed>>
     */
    public function deleteIndexes(bool $dryRun = false): array
    {
        return $this->eachClass(fn (string $className) => $this->deleteObjectIndexes($className, $dryRun));
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteObjectIndexes(string $className, bool $dryRun = false): array
    {
        $diff = $this->diff($className, false);

        if ($diff['skipped'] !== null) {
            return $this->report($diff);
        }

        // Only the mapped indexes Parse actually holds may be deleted: submitting a
        // delete for an unknown name is rejected by the server.
        $diff['dropped'] = array_values(array_intersect(array_keys($diff['desired']), array_keys($diff['existing'])));
        $diff['create'] = [];
        $diff['mismatched'] = [];
        $diff['unchanged'] = [];

        if ($diff['dropped'] === []) {
            return $this->report($diff);
        }

        $this->write($diff['class'], $diff['schema'], $diff['exists'], [], $diff['dropped'], [], $dryRun);

        return $this->report($diff);
    }

    /**
     * Removes the internal entries the callers have no use for.
     *
     * @param array<string, mixed> $diff
     *
     * @return array<string, mixed>
     */
    private function report(array $diff): array
    {
        unset($diff['class'], $diff['schema']);

        return $diff;
    }

    /**
     * @param callable(string): array<string, mixed> $callback
     *
     * @return array<string, array<string, mixed>>
     */
    private function eachClass(callable $callback): array
    {
        $results = [];

        foreach ($this->cmf->getAllMetadata() as $class) {
            assert($class instanceof ClassMetadata);

            if ($class->isMappedSuperclass || $class->isEmbeddedDocument) {
                continue;
            }

            $results[$class->name] = $callback($class->name);
        }

        return $results;
    }

    /**
     * Computes what has to change for a class. Mapping errors are raised here, before
     * any write, so that a dry run reports them too.
     *
     * @return array<string, mixed>
     */
    private function diff(string $className, bool $createFields): array
    {
        $class = $this->om->getClassMetadata($className);

        $result = [
            'class' => $class,
            'schema' => null,
            'exists' => false,
            'desired' => [],
            'existing' => [],
            'create' => [],
            'dropped' => [],
            'unchanged' => [],
            'mismatched' => [],
            'unmanaged' => [],
            'fields' => [],
            'skipped' => null,
        ];

        if ($class->isMappedSuperclass || $class->isEmbeddedDocument) {
            $result['skipped'] = 'mapped superclass';

            return $result;
        }

        if (!$class->getCollection()) {
            $result['skipped'] = 'no collection mapped';

            return $result;
        }

        // May throw a MappingException: this has to happen before any HTTP call.
        $result['desired'] = $this->indexMapper->getParseIndexes($class);

        if ($result['desired'] === [] && !$createFields) {
            $result['skipped'] = 'no index mapped';

            return $result;
        }

        $this->assertMasterKey();

        $result['schema'] = new ParseSchema($class->getCollection());
        $remote = $this->fetchSchema($class, $result['schema']);

        if ($remote === null) {
            $result['create'] = $result['desired'];
            $result['fields'] = $createFields ? $this->getMissingFields($class, []) : [];

            return $result;
        }

        $result['exists'] = true;
        $result['existing'] = $this->filterManagedIndexes($class, $remote['indexes'] ?? []);
        $result['fields'] = $createFields ? $this->getMissingFields($class, $remote['fields'] ?? []) : [];

        foreach ($result['desired'] as $name => $spec) {
            if (!isset($result['existing'][$name])) {
                $result['create'][$name] = $spec;
            } elseif (!$this->specsEqual($result['existing'][$name], $spec)) {
                $result['mismatched'][$name] = ['from' => $result['existing'][$name], 'to' => $spec];
            } else {
                $result['unchanged'][] = $name;
            }
        }

        // Reported for information only: an index absent from the mapping is never dropped.
        $result['unmanaged'] = array_keys(array_diff_key($result['existing'], $result['desired']));

        return $result;
    }

    /**
     * Applies the pending fields, drops and creations.
     *
     * Drops and creations can not share a payload: Parse refuses to delete and add the
     * same index name at once. Fields and indexes on the other hand travel together,
     * which is what makes an index on a brand new column possible.
     *
     * @param array<string, array{type: string, targetDocument?: string}> $fields
     * @param string[]                                                   $drop
     * @param array<string, array<string, int|string>>                    $create
     */
    private function write(
        ClassMetadata $class,
        ParseSchema $schema,
        bool $exists,
        array $fields,
        array $drop,
        array $create,
        bool $dryRun
    ): void {
        if ($dryRun) {
            return;
        }

        try {
            if (!$exists) {
                $this->addFields($schema, $fields);
                foreach ($create as $name => $spec) {
                    $schema->addIndex($name, $spec);
                }
                $schema->save();

                return;
            }

            if ($fields !== [] || $drop !== []) {
                $this->addFields($schema, $fields);
                foreach ($drop as $name) {
                    $schema->deleteIndex($name);
                }
                $schema->update();
            }

            if ($create !== []) {
                foreach ($create as $name => $spec) {
                    $schema->addIndex($name, $spec);
                }
                $schema->update();
            }
        } catch (ParseException $e) {
            if ($e->getCode() === self::ERROR_OPERATION_FORBIDDEN) {
                throw new RedkingParseException(sprintf(
                    'Parse refused to update the schema of "%s". The server is most likely started with '
                    .'lockSchemas enabled.',
                    $class->getCollection()
                ), $e->getCode(), $e);
            }

            throw $e;
        }
    }

    /**
     * @param array<string, array{type: string, targetDocument?: string}> $fields
     */
    private function addFields(ParseSchema $schema, array $fields): void
    {
        foreach ($fields as $name => $definition) {
            if (in_array($definition['type'], ['Pointer', 'Relation'], true)) {
                $targetCollection = $this->om->getClassMetadata($definition['targetDocument'])->getCollection();

                if ($definition['type'] === 'Pointer') {
                    $schema->addPointer($name, $targetCollection);
                } else {
                    $schema->addRelation($name, $targetCollection);
                }

                continue;
            }

            $schema->addField($name, $definition['type']);
        }
    }

    /**
     * Returns the mapped fields Parse does not hold yet. Existing fields are never
     * modified: Parse can not change the type of a field.
     *
     * @param array<string, mixed> $remoteFields
     *
     * @return array<string, array{type: string, targetDocument?: string}>
     */
    private function getMissingFields(ClassMetadata $class, array $remoteFields): array
    {
        $missing = [];

        foreach ($this->indexMapper->getParseFields($class) as $name => $definition) {
            if (isset($remoteFields[$name])) {
                continue;
            }

            if (isset($definition['targetDocument']) && !class_exists($definition['targetDocument'])) {
                continue;
            }

            $missing[$name] = $definition;
        }

        return $missing;
    }

    /**
     * Fetches the Parse schema of a class, or null when the class does not exist yet.
     *
     * @return array<string, mixed>|null
     */
    private function fetchSchema(ClassMetadata $class, ?ParseSchema $schema = null): ?array
    {
        $schema = $schema ?? new ParseSchema($class->getCollection());

        try {
            return $schema->get();
        } catch (ParseException $e) {
            if ($e->getCode() === self::ERROR_INVALID_CLASS_NAME) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Removes the indexes the bundle never manages: the primary key and the ones Parse
     * creates for its own classes.
     *
     * @param array<string, mixed> $indexes
     *
     * @return array<string, array<string, int|string>>
     */
    private function filterManagedIndexes(ClassMetadata $class, array $indexes): array
    {
        unset($indexes['_id_']);

        $protected = self::PROTECTED_INDEXES[$class->getCollection()] ?? [];

        return array_diff_key($indexes, array_flip($protected));
    }

    /**
     * The order of the keys drives the prefix semantics of a compound index, so a plain
     * comparison of associative arrays would not be enough.
     *
     * @param array<string, int|string> $existing
     * @param array<string, int|string> $desired
     */
    private function specsEqual(array $existing, array $desired): bool
    {
        if (array_keys($existing) !== array_keys($desired)) {
            return false;
        }

        return array_map([$this, 'normalizeOrder'], array_values($existing))
            === array_map([$this, 'normalizeOrder'], array_values($desired));
    }

    /**
     * @param int|string|float $order
     *
     * @return int|string
     */
    private function normalizeOrder($order)
    {
        return is_numeric($order) ? (int) $order : $order;
    }

    /**
     * ParseSchema always uses the master key: without one every schema call is rejected
     * with an opaque error.
     */
    private function assertMasterKey(): void
    {
        $parameters = $this->om->getConfiguration()->getConnectionParameters();

        if (empty($parameters['master_key'])) {
            throw new RedkingParseException(
                'Schema operations require a master key. Set "redking_parse.master_key" in your configuration.'
            );
        }
    }
}
