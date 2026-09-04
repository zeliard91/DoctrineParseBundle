<?php

namespace Redking\ParseBundle\Tests\Functional;

use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\Mapping\Annotations as ORM;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\Mapping\Driver\AttributeDriver;
use Redking\ParseBundle\Mapping\MappingException;
use Redking\ParseBundle\Tests\Models\Blog\ChainNode;

#[ORM\ParseObject(collection: 'driver_fixture', repositoryClass: 'CustomRepo')]
class AttributeDriverDocumentFixture
{
    use \Redking\ParseBundle\ObjectTrait;

    #[ORM\Field(type: 'string')]
    private ?string $title = null;
}

#[ORM\MappedSuperclass]
class AttributeDriverSuperclassFixture
{
    #[ORM\Field(type: 'string')]
    private ?string $secret = null;
}

#[ORM\ParseObject(collection: 'driver_index_fixture')]
#[ORM\Index(keys: ['title' => 'asc', 'rank' => 'desc'])]
#[ORM\Index(keys: ['rank' => 'asc'], name: 'explicit_name')]
class AttributeDriverIndexFixture
{
    use \Redking\ParseBundle\ObjectTrait;

    #[ORM\Field(type: 'string')]
    #[ORM\Index]
    private ?string $title = null;

    #[ORM\Field(type: 'integer', name: 'rank')]
    #[ORM\Index(order: 'desc')]
    #[ORM\Index(keys: ['position' => 'asc'], name: 'from_keys')]
    private ?int $position = null;
}

#[ORM\ParseObject(collection: 'driver_object_index_fixture', indexes: [new ORM\Index(keys: ['title' => 'asc'], name: 'from_object_attribute')])]
class AttributeDriverObjectIndexFixture
{
    use \Redking\ParseBundle\ObjectTrait;

    #[ORM\Field(type: 'string')]
    private ?string $title = null;
}

#[ORM\ParseObject(collection: 'driver_bad_index_fixture')]
class AttributeDriverIndexOnUnmappedPropertyFixture
{
    use \Redking\ParseBundle\ObjectTrait;

    #[ORM\Index]
    private ?string $notAField = null;
}

/**
 * Coverage for AttributeDriver: transience detection, the factory, and the
 * loadMetadataForClass branches (collection/repositoryClass, mapped superclass,
 * non-document guard). Pure reflection — no Parse Server.
 */
class AttributeDriverTest extends TestCase
{
    private function driver(): AttributeDriver
    {
        return AttributeDriver::create([]);
    }

    private function metadataFor(string $class): ClassMetadata
    {
        $cm = new ClassMetadata($class);
        $cm->initializeReflection(new RuntimeReflectionService());

        return $cm;
    }

    public function testCreateReturnsDriver(): void
    {
        $this->assertInstanceOf(AttributeDriver::class, AttributeDriver::create([]));
    }

    public function testIsTransient(): void
    {
        $driver = $this->driver();

        $this->assertFalse($driver->isTransient(ChainNode::class), 'a mapped document is not transient');
        $this->assertTrue($driver->isTransient(\stdClass::class), 'a plain class is transient');
    }

    public function testLoadMetadataMapsCollectionRepositoryAndFields(): void
    {
        $cm = $this->metadataFor(AttributeDriverDocumentFixture::class);

        $this->driver()->loadMetadataForClass(AttributeDriverDocumentFixture::class, $cm);

        $this->assertSame('driver_fixture', $cm->getCollection());
        $this->assertStringEndsWith('\\CustomRepo', $cm->customRepositoryClassName);
        $this->assertSame('id', $cm->identifier);
        $this->assertTrue($cm->hasField('title'));
        $this->assertSame('string', $cm->getTypeOfField('title'));
        $this->assertFalse($cm->isMappedSuperclass);
    }

    public function testLoadMetadataFlagsMappedSuperclass(): void
    {
        $cm = $this->metadataFor(AttributeDriverSuperclassFixture::class);

        $this->driver()->loadMetadataForClass(AttributeDriverSuperclassFixture::class, $cm);

        $this->assertTrue($cm->isMappedSuperclass);
    }

    public function testLoadMetadataRejectsNonDocument(): void
    {
        $cm = $this->metadataFor(\stdClass::class);

        $this->expectException(MappingException::class);
        $this->driver()->loadMetadataForClass(\stdClass::class, $cm);
    }

    public function testLoadMetadataReadsRepeatedClassLevelIndexes(): void
    {
        $cm = $this->metadataFor(AttributeDriverIndexFixture::class);

        $this->driver()->loadMetadataForClass(AttributeDriverIndexFixture::class, $cm);

        $this->assertTrue($cm->hasIndexes());
        $this->assertContains(
            ['keys' => ['title' => 1, 'rank' => -1], 'options' => []],
            $cm->getIndexes(),
            'a class level #[Index] is read'
        );
        $this->assertContains(
            ['keys' => ['rank' => 1], 'options' => ['name' => 'explicit_name']],
            $cm->getIndexes(),
            'a repeated class level #[Index] is read too, and its name lands in the options'
        );
    }

    public function testLoadMetadataDesugarsPropertyLevelIndexes(): void
    {
        $cm = $this->metadataFor(AttributeDriverIndexFixture::class);

        $this->driver()->loadMetadataForClass(AttributeDriverIndexFixture::class, $cm);

        // A property index is declared with the PHP field name: resolving it to the
        // Parse column name is the IndexMapper's job.
        $this->assertContains(
            ['keys' => ['title' => 1], 'options' => []],
            $cm->getIndexes(),
            'a bare property #[Index] defaults to an ascending single key index'
        );
        $this->assertContains(
            ['keys' => ['position' => -1], 'options' => []],
            $cm->getIndexes(),
            'the order argument is honoured'
        );
        $this->assertContains(
            ['keys' => ['position' => 1], 'options' => ['name' => 'from_keys']],
            $cm->getIndexes(),
            'explicit keys win over the property name'
        );
    }

    public function testLoadMetadataReadsIndexesDeclaredOnTheParseObjectAttribute(): void
    {
        $cm = $this->metadataFor(AttributeDriverObjectIndexFixture::class);

        $this->driver()->loadMetadataForClass(AttributeDriverObjectIndexFixture::class, $cm);

        $this->assertSame(
            [['keys' => ['title' => 1], 'options' => ['name' => 'from_object_attribute']]],
            $cm->getIndexes()
        );
    }

    public function testLoadMetadataRejectsAnIndexOnAnUnmappedProperty(): void
    {
        $cm = $this->metadataFor(AttributeDriverIndexOnUnmappedPropertyFixture::class);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('declares an index but has no field mapping');
        $this->driver()->loadMetadataForClass(AttributeDriverIndexOnUnmappedPropertyFixture::class, $cm);
    }
}
