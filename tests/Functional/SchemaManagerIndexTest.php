<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseException;
use Parse\ParseSchema;
use Redking\ParseBundle\Mapping\MappingException;
use Redking\ParseBundle\Tests\Models\Blog\IndexedArticle;
use Redking\ParseBundle\Tests\Models\Blog\User;
use Redking\ParseBundle\Tests\TestCase;

/**
 * Propagation of the mapped indexes to a real Parse Server.
 */
class SchemaManagerIndexTest extends TestCase
{
    private const COLLECTION = 'blog_indexed_article';

    public function setUp(): void
    {
        parent::setUp();

        $this->dropTestClass();
    }

    public function tearDown(): void
    {
        $this->dropTestClass();

        parent::tearDown();
    }

    private function dropTestClass(): void
    {
        try {
            (new ParseSchema(self::COLLECTION))->purge();
            (new ParseSchema(self::COLLECTION))->delete();
        } catch (ParseException $e) {
            // The class does not exist yet, which is exactly what we want.
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function remoteIndexes(): array
    {
        return (new ParseSchema(self::COLLECTION))->get()['indexes'] ?? [];
    }

    public function testEnsureCreatesTheClassItsFieldsAndItsIndexes()
    {
        $report = $this->om->getSchemaManager()->ensureObjectIndexes(IndexedArticle::class);

        $this->assertFalse($report['exists'], 'the class did not exist yet');
        $this->assertSame(['title_author', 'title_1', 'hits_-1'], array_keys($report['create']));
        $this->assertSame(['title', 'hits', 'published', 'author'], array_keys($report['fields']));

        $remote = $this->remoteIndexes();

        $this->assertSame(['title' => 1, '_p_author' => 1], $remote['title_author'], 'a Pointer is indexed on its "_p_" column');
        $this->assertSame(['title' => 1], $remote['title_1']);
        $this->assertSame(['hits' => -1], $remote['hits_-1'], 'the Parse name of a renamed field is used');

        $fields = (new ParseSchema(self::COLLECTION))->get()['fields'];

        $this->assertSame('Number', $fields['hits']['type']);
        $this->assertSame('Pointer', $fields['author']['type']);
        $this->assertSame('_User', $fields['author']['targetClass']);
    }

    /**
     * Parse rejects an index name it already holds, so a second run must not resubmit
     * anything.
     */
    public function testEnsureIsIdempotent()
    {
        $schemaManager = $this->om->getSchemaManager();
        $schemaManager->ensureObjectIndexes(IndexedArticle::class);

        $report = $schemaManager->ensureObjectIndexes(IndexedArticle::class);

        $this->assertTrue($report['exists']);
        $this->assertSame([], $report['create']);
        $this->assertSame([], $report['fields']);
        $this->assertSame([], $report['mismatched']);
        $this->assertSame(['title_author', 'title_1', 'hits_-1'], $report['unchanged']);
        $this->assertCount(3, $this->remoteIndexes());
    }

    public function testDryRunWritesNothing()
    {
        $report = $this->om->getSchemaManager()->ensureObjectIndexes(IndexedArticle::class, true, true);

        $this->assertSame(['title_author', 'title_1', 'hits_-1'], array_keys($report['create']));

        $this->expectException(ParseException::class);
        (new ParseSchema(self::COLLECTION))->get();
    }

    public function testUpdateRecreatesAnIndexWhoseKeysChanged()
    {
        $schemaManager = $this->om->getSchemaManager();
        $schemaManager->ensureObjectIndexes(IndexedArticle::class);

        // Same index name, opposite direction.
        $class = $this->om->getClassMetadata(IndexedArticle::class);
        $class->indexes = [];
        $class->addIndex(['title' => 'desc'], ['name' => 'title_1']);

        $report = $schemaManager->updateObjectIndexes(IndexedArticle::class);

        $this->assertSame(['title_1'], $report['dropped']);
        $this->assertSame(['title_1' => ['title' => -1]], $report['create']);
        $this->assertSame(['title' => -1], $this->remoteIndexes()['title_1']);
    }

    public function testEnsureReportsAMismatchWithoutTouchingIt()
    {
        $schemaManager = $this->om->getSchemaManager();
        $schemaManager->ensureObjectIndexes(IndexedArticle::class);

        $class = $this->om->getClassMetadata(IndexedArticle::class);
        $class->indexes = [];
        $class->addIndex(['title' => 'desc'], ['name' => 'title_1']);

        $report = $schemaManager->ensureObjectIndexes(IndexedArticle::class);

        $this->assertSame(
            ['title_1' => ['from' => ['title' => 1], 'to' => ['title' => -1]]],
            $report['mismatched']
        );
        $this->assertSame([], $report['dropped']);
        $this->assertSame(['title' => 1], $this->remoteIndexes()['title_1'], 'the existing index is left alone');
    }

    public function testDeleteDropsTheMappedIndexesOnly()
    {
        $schemaManager = $this->om->getSchemaManager();
        $schemaManager->ensureObjectIndexes(IndexedArticle::class);

        // An index created outside of the mapping must survive.
        $schema = new ParseSchema(self::COLLECTION);
        $schema->addIndex('manual_idx', ['published' => 1]);
        $schema->update();

        $report = $schemaManager->deleteObjectIndexes(IndexedArticle::class);

        $this->assertSame(['title_author', 'title_1', 'hits_-1'], $report['dropped']);
        $this->assertSame(['manual_idx'], $report['unmanaged']);
        $this->assertSame(['manual_idx' => ['published' => 1]], $this->remoteIndexes());
    }

    public function testAnIndexOnATimestampFieldIsRejected()
    {
        $class = $this->om->getClassMetadata(IndexedArticle::class);
        $class->indexes = [];
        $class->addIndex(['createdAt' => 'desc']);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('"_created_at"/"_updated_at"');
        $this->om->getSchemaManager()->ensureObjectIndexes(IndexedArticle::class);
    }

    /**
     * The indexes Parse maintains on its own classes are never reported as managed, so
     * they can neither be dropped nor compared against the mapping.
     */
    public function testTheBuiltInIndexesOfParseClassesAreNeverManaged()
    {
        $indexes = $this->om->getSchemaManager()->getExistingObjectIndexes(User::class);

        foreach (['_id_', 'username_1', 'email_1', 'case_insensitive_username', 'case_insensitive_email'] as $name) {
            $this->assertArrayNotHasKey($name, $indexes);
        }
    }
}
