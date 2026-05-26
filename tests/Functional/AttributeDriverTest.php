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
}
