<?php

namespace Redking\ParseBundle\Tests\Mapping;

use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\Mapping\Annotations as ORM;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\Mapping\Driver\AttributeDriver;
use Redking\ParseBundle\Mapping\IndexMapper;
use Redking\ParseBundle\Mapping\MappingException;
use Redking\ParseBundle\Tests\Models\Blog\IndexedArticle;
use Redking\ParseBundle\Tests\Models\Blog\User;

#[ORM\ParseObject(collection: 'index_mapper_fixture')]
class IndexMapperFixture
{
    use \Redking\ParseBundle\ObjectTrait;

    #[ORM\Field(type: 'string')]
    private ?string $title = null;

    #[ORM\Field(type: 'integer', name: 'rank')]
    private ?int $position = null;

    #[ORM\Field(type: 'geopoint')]
    private $location = null;

    #[ORM\ReferenceOne(targetDocument: User::class)]
    private $author;

    #[ORM\ReferenceMany(targetDocument: User::class)]
    private $readers;

    #[ORM\ReferenceMany(targetDocument: User::class, implementation: 'relation')]
    private $followers;

    #[ORM\ReferenceOne(targetDocument: User::class, mappedBy: 'avatar')]
    private $owner;
}

/**
 * Coverage for the translation of mapped indexes into the Parse/MongoDB column names.
 * No Parse Server needed.
 */
class IndexMapperTest extends TestCase
{
    private IndexMapper $mapper;

    public function setUp(): void
    {
        $this->mapper = new IndexMapper();
    }

    private function metadataFor(string $className): ClassMetadata
    {
        $cm = new ClassMetadata($className);
        $cm->initializeReflection(new RuntimeReflectionService());
        AttributeDriver::create([])->loadMetadataForClass($className, $cm);

        return $cm;
    }

    private function fixture(array $keys, array $options = []): ClassMetadata
    {
        $cm = $this->metadataFor(IndexMapperFixture::class);
        $cm->indexes = [];
        $cm->addIndex($keys, $options);

        return $cm;
    }

    public function testAPlainFieldKeepsItsName(): void
    {
        $this->assertSame(
            ['title_1' => ['title' => 1]],
            $this->mapper->getParseIndexes($this->fixture(['title' => 'asc']))
        );
    }

    public function testARenamedFieldResolvesToItsParseName(): void
    {
        $this->assertSame(
            ['rank_-1' => ['rank' => -1]],
            $this->mapper->getParseIndexes($this->fixture(['position' => 'desc']))
        );
    }

    public function testAFieldCanAlsoBeDeclaredWithItsParseName(): void
    {
        $this->assertSame(
            ['rank_1' => ['rank' => 1]],
            $this->mapper->getParseIndexes($this->fixture(['rank' => 'asc']))
        );
    }

    /**
     * MongoDB stores a Parse Pointer in a "_p_" prefixed column, and Parse does not
     * translate index keys, so the prefix has to be part of the specification.
     */
    public function testAnOwningReferenceOneIsPrefixed(): void
    {
        $this->assertSame(
            ['_p_author_1' => ['_p_author' => 1]],
            $this->mapper->getParseIndexes($this->fixture(['author' => 'asc']))
        );
    }

    public function testAnArrayOfPointersKeepsItsName(): void
    {
        $this->assertSame(
            ['readers_1' => ['readers' => 1]],
            $this->mapper->getParseIndexes($this->fixture(['readers' => 'asc']))
        );
    }

    public function testCompoundKeysKeepTheirOrder(): void
    {
        $indexes = $this->mapper->getParseIndexes($this->fixture(['title' => 'asc', 'author' => 'desc']));

        $this->assertSame(['title_1__p_author_-1' => ['title' => 1, '_p_author' => -1]], $indexes);
        $this->assertSame(['title', '_p_author'], array_keys($indexes['title_1__p_author_-1']));
    }

    public function testAnExplicitNameWins(): void
    {
        $this->assertSame(
            ['my_index' => ['title' => 1]],
            $this->mapper->getParseIndexes($this->fixture(['title' => 'asc'], ['name' => 'my_index']))
        );
    }

    public function testARelationCanNotBeIndexed(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('implemented as a Parse Relation');
        $this->mapper->getParseIndexes($this->fixture(['followers' => 'asc']));
    }

    public function testAnInverseSideReferenceCanNotBeIndexed(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('inverse side of the association');
        $this->mapper->getParseIndexes($this->fixture(['owner' => 'asc']));
    }

    public function testTheIdentifierCanNotBeIndexed(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('stored as the MongoDB "_id" column');
        $this->mapper->getParseIndexes($this->fixture(['id' => 'asc']));
    }

    /**
     * @dataProvider provideTimestampFields
     */
    public function testTimestampFieldsCanNotBeIndexed(string $fieldName): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('"_created_at"/"_updated_at"');
        $this->mapper->getParseIndexes($this->fixture([$fieldName => 'asc']));
    }

    public static function provideTimestampFields(): array
    {
        return [['createdAt'], ['updatedAt']];
    }

    public function testAnUnmappedFieldIsRejected(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('which is not mapped');
        $this->mapper->getParseIndexes($this->fixture(['nope' => 'asc']));
    }

    public function testAGeoPointRequiresA2dsphereIndex(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Invalid index order "1"');
        $this->mapper->getParseIndexes($this->fixture(['location' => 'asc']));
    }

    public function testAGeoPointAcceptsA2dsphereIndex(): void
    {
        $this->assertSame(
            ['location_2dsphere' => ['location' => '2dsphere']],
            $this->mapper->getParseIndexes($this->fixture(['location' => '2dsphere']))
        );
    }

    public function testTwoIndexesSharingAGeneratedNameAreRejected(): void
    {
        $cm = $this->metadataFor(IndexMapperFixture::class);
        $cm->indexes = [];
        $cm->addIndex(['title' => 'asc'], ['name' => 'clash']);
        $cm->addIndex(['rank' => 'asc'], ['name' => 'clash']);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('resolve to the name "clash"');
        $this->mapper->getParseIndexes($cm);
    }

    /**
     * An index inherited from a mapped superclass may be declared twice on a single
     * Parse class: an identical duplicate is simply collapsed.
     */
    public function testAnIdenticalDuplicateIsCollapsed(): void
    {
        $cm = $this->metadataFor(IndexMapperFixture::class);
        $cm->indexes = [];
        $cm->addIndex(['title' => 'asc']);
        $cm->addIndex(['title' => 'asc']);

        $this->assertSame(['title_1' => ['title' => 1]], $this->mapper->getParseIndexes($cm));
    }

    public function testAnOverlongGeneratedNameIsRejected(): void
    {
        $longFieldName = str_repeat('a', 130);

        $cm = $this->metadataFor(IndexMapperFixture::class);
        $cm->mapField(['fieldName' => $longFieldName, 'type' => 'string']);
        $cm->indexes = [];
        $cm->addIndex([$longFieldName => 'asc']);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('exceeds 127 characters');
        $this->mapper->getParseIndexes($cm);
    }

    public function testFieldsAreMappedToParseTypes(): void
    {
        $fields = $this->mapper->getParseFields($this->metadataFor(IndexMapperFixture::class));

        $this->assertSame(
            [
                'title' => ['type' => 'String'],
                'rank' => ['type' => 'Number'],
                'location' => ['type' => 'GeoPoint'],
                'author' => ['type' => 'Pointer', 'targetDocument' => User::class],
                'readers' => ['type' => 'Array'],
                'followers' => ['type' => 'Relation', 'targetDocument' => User::class],
            ],
            $fields,
            'the identifier, the timestamps and the inverse sides are left out'
        );
    }

    public function testIndexesOfARealModelAreResolved(): void
    {
        $this->assertSame(
            [
                'title_author' => ['title' => 1, '_p_author' => 1],
                'title_1' => ['title' => 1],
                'hits_-1' => ['hits' => -1],
            ],
            $this->mapper->getParseIndexes($this->metadataFor(IndexedArticle::class))
        );
    }
}
