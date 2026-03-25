<?php

namespace Redking\ParseBundle\Tests\Mapping\Driver;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Redking\ParseBundle\Mapping\ClassMetadata;

// require_once 'fixtures/InvalidPartialFilterDocument.php';
// require_once 'fixtures/PartialFilterDocument.php';
require_once 'fixtures/User.php';
// require_once 'fixtures/EmbeddedDocument.php';
// require_once 'fixtures/QueryResultDocument.php';

abstract class AbstractDriverTest extends BaseTestCase
{
    protected $driver;

    public function setUp(): void
    {
        // implement driver setup and metadata read
    }

    public function tearDown(): void
    {
        unset ($this->driver);
    }

    public function testDriver()
    {

        $classMetadata = new ClassMetadata('\TestObjects\User');
        $this->driver->loadMetadataForClass('TestObjects\User', $classMetadata);

        $this->assertEquals(array(
            'fieldName' => 'id',
            'id' => true,
            'name' => '_objectId',
            'type' => 'string',
            'isCascadeDetach' => false,
            'isCascadeMerge' => false,
            'isCascadePersist' => false,
            'isCascadeRefresh' => false,
            'isCascadeRemove' => false,
            'isInverseSide' => false,
            'isOwningSide' => true,
            'nullable' => false
        ), $classMetadata->fieldMappings['id']);

        $this->assertEquals(array(
            'fieldName' => 'username',
            'name' => 'username',
            'type' => 'string',
            'isCascadeDetach' => false,
            'isCascadeMerge' => false,
            'isCascadePersist' => false,
            'isCascadeRefresh' => false,
            'isCascadeRemove' => false,
            'isInverseSide' => false,
            'isOwningSide' => true,
            'nullable' => false,
        ), $classMetadata->fieldMappings['username']);
        
        $this->assertEquals(array(
            'fieldName' => 'createdAt',
            'name' => 'createdAt',
            'type' => 'DateTime',
            'isCascadeDetach' => false,
            'isCascadeMerge' => false,
            'isCascadePersist' => false,
            'isCascadeRefresh' => false,
            'isCascadeRemove' => false,
            'isInverseSide' => false,
            'isOwningSide' => true,
            'nullable' => false,
        ), $classMetadata->fieldMappings['createdAt']);
        
        $this->assertEquals(array(
            'fieldName' => 'updatedAt',
            'name' => 'updatedAt',
            'type' => 'DateTime',
            'isCascadeDetach' => false,
            'isCascadeMerge' => false,
            'isCascadePersist' => false,
            'isCascadeRefresh' => false,
            'isCascadeRemove' => false,
            'isInverseSide' => false,
            'isOwningSide' => true,
            'nullable' => false,
        ), $classMetadata->fieldMappings['updatedAt']);

        $this->assertEquals(array(
            'fieldName' => 'tags',
            'name' => 'tags',
            'type' => 'array',
            'isCascadeDetach' => false,
            'isCascadeMerge' => false,
            'isCascadePersist' => false,
            'isCascadeRefresh' => false,
            'isCascadeRemove' => false,
            'isInverseSide' => false,
            'isOwningSide' => true,
            'nullable' => false,
        ), $classMetadata->fieldMappings['tags']);

        $this->assertEquals(array(
            'association' => ClassMetadata::REFERENCE_ONE,
            'fieldName' => 'address',
            'name' => 'address',
            'type' => 'one',
            'targetDocument' => 'TestObjects\Address',
            'isInverseSide' => false,
            'isOwningSide' => true,
            'nullable' => false,
            'cascade' => null,
            'orphanRemoval' => false,
            'reference' => true,
            'simple' => false,
            'inversedBy' => null,
            'mappedBy' => null,
            'repositoryMethod' => null,
            'limit' => null,
            'skip' => null,
            'isCascadeRemove' => false,
            'isCascadePersist' => false,
            'isCascadeRefresh' => false,
            'isCascadeMerge' => false,
            'isCascadeDetach' => false,
            'discriminatorField' => null,
            'discriminatorMap' => null,
            'defaultDiscriminatorValue' => null,
            'sort' => [],
            'criteria' => [],
            'fetch' => ClassMetadata::FETCH_LAZY,
        ), $classMetadata->fieldMappings['address']);

        $this->assertEquals(array(
            'association' => ClassMetadata::REFERENCE_MANY,
            'fieldName' => 'phoneNumbers',
            'name' => 'phoneNumbers',
            'type' => 'many',
            'targetDocument' => 'TestObjects\PhoneNumber',
            'isInverseSide' => false,
            'isOwningSide' => true,
            'lazyLoad' => true,
            'nullable' => false,
            'cascade' => [
                'remove',
                'persist',
                'refresh',
                'merge',
                'detach',
            ],
            'orphanRemoval' => false,
            'reference' => true,
            'simple' => false,
            'inversedBy' => null,
            'mappedBy' => null,
            'repositoryMethod' => null,
            'limit' => null,
            'skip' => null,
            'isCascadeRemove' => true,
            'isCascadePersist' => true,
            'isCascadeRefresh' => true,
            'isCascadeMerge' => true,
            'isCascadeDetach' => true,
            'discriminatorField' => null,
            'discriminatorMap' => null,
            'defaultDiscriminatorValue' => null,
            'sort' => [],
            'criteria' => [],
            'fetch' => ClassMetadata::FETCH_LAZY,
            'implementation' => ClassMetadata::ASSOCIATION_IMPL_ARRAY,
            'includeKeys' => null
        ), $classMetadata->fieldMappings['phoneNumbers']);
    }
}
