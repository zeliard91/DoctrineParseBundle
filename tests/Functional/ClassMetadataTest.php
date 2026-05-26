<?php

namespace Redking\ParseBundle\Tests\Functional;

use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\Mapping\MappingException;
use Redking\ParseBundle\Tests\Models\Blog\Article;
use Redking\ParseBundle\Tests\Models\Blog\ChainNode;
use Redking\ParseBundle\Tests\Models\Blog\Tag;

/**
 * Coverage for the ClassMetadata accessor surface, exercised against the real
 * mapping of the Blog test models (no Parse Server round-trip). Each test runs
 * on a fresh ObjectManager (per setUp), so mutating accessors stay isolated.
 */
class ClassMetadataTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [];

    private function chainNode(): ClassMetadata
    {
        return $this->om->getClassMetadata(ChainNode::class);
    }

    public function testIdentifierAndName(): void
    {
        $cm = $this->chainNode();

        $this->assertSame(ChainNode::class, $cm->getName());
        $this->assertSame(['id'], $cm->getIdentifier());
        $this->assertSame(['id'], $cm->getIdentifierFieldNames());
        $this->assertTrue($cm->isIdentifier('id'));
        $this->assertFalse($cm->isIdentifier('label'));
    }

    public function testReflectionAccessors(): void
    {
        $cm = $this->chainNode();

        $this->assertSame(ChainNode::class, $cm->getReflectionClass()->getName());
        $this->assertInstanceOf(\ReflectionProperty::class, $cm->getReflectionProperty('label'));
        $this->assertArrayHasKey('label', $cm->getReflectionProperties());

        $node = new ChainNode();
        $cm->setFieldValue($node, 'label', 'hello');
        $this->assertSame('hello', $cm->getFieldValue($node, 'label'));
    }

    public function testFieldIntrospection(): void
    {
        $cm = $this->chainNode();

        $this->assertTrue($cm->hasField('label'));
        $this->assertFalse($cm->hasField('nope'));
        $this->assertContains('label', $cm->getFieldNames());

        $this->assertSame('string', $cm->getTypeOfField('label'));
        $this->assertNull($cm->getTypeOfField('nope'));
        $this->assertSame('label', $cm->getNameOfField('label'));
        $this->assertNull($cm->getNameOfField('nope'));

        // The identifier is stored under the Parse "_objectId" name.
        $this->assertSame('id', $cm->getFieldNameOfName('_objectId'));

        $this->assertFalse($cm->isNullable('label'));
        $this->assertFalse($cm->isInheritedField('label'));

        $mapping = $cm->getFieldMapping('label');
        $this->assertSame('string', $mapping['type']);
    }

    public function testGetFieldMappingThrowsForUnknownField(): void
    {
        $this->expectException(MappingException::class);
        $this->chainNode()->getFieldMapping('nope');
    }

    public function testFieldTypePredicates(): void
    {
        $cm = $this->chainNode();

        $this->assertFalse($cm->isFieldAFile('label'));
        $this->assertFalse($cm->isFieldAnArray('label'));
        $this->assertFalse($cm->isFieldAnHash('label'));
        $this->assertFalse($cm->isFieldAnObject('label'));
    }

    public function testAssociationIntrospection(): void
    {
        $cm = $this->chainNode();

        $this->assertTrue($cm->hasAssociation('first'));
        $this->assertFalse($cm->hasAssociation('label'));
        $this->assertTrue($cm->isSingleValuedAssociation('first'));
        $this->assertFalse($cm->isCollectionValuedAssociation('first'));
        $this->assertContains('first', $cm->getAssociationNames());
        $this->assertSame(ChainNode::class, $cm->getAssociationTargetClass('first'));

        $mapping = $cm->getAssociationMapping('first');
        $this->assertSame(ChainNode::class, $mapping['targetDocument']);

        // Owning ReferenceMany on Article.
        $article = $this->om->getClassMetadata(Article::class);
        $this->assertTrue($article->isCollectionValuedAssociation('tags'));
        $this->assertTrue($article->isOwningCollectionValuedAssociation('tags'));
    }

    public function testGetAssociationTargetClassThrowsForNonAssociation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->chainNode()->getAssociationTargetClass('label');
    }

    public function testIdentifierValueHelpers(): void
    {
        $cm = $this->chainNode();
        $node = $cm->newInstance();
        $this->assertInstanceOf(ChainNode::class, $node);

        $cm->setIdentifierValue($node, 'ABC123');
        $this->assertSame('ABC123', $cm->getIdentifierValue($node));
        $this->assertSame(['id' => 'ABC123'], $cm->getIdentifierValues($node));
        $this->assertSame('ABC123', $cm->getIdentifierObject($node));
    }

    public function testChangeTrackingPolicy(): void
    {
        $cm = $this->chainNode();

        $this->assertTrue($cm->isChangeTrackingDeferredImplicit());
        $this->assertFalse($cm->isChangeTrackingDeferredExplicit());
        $this->assertFalse($cm->isChangeTrackingNotify());

        $cm->setChangeTrackingPolicy(ClassMetadata::CHANGETRACKING_NOTIFY);
        $this->assertTrue($cm->isChangeTrackingNotify());
        $this->assertFalse($cm->isChangeTrackingDeferredImplicit());
    }

    public function testInheritanceTypeFlags(): void
    {
        $cm = $this->chainNode();

        $this->assertTrue($cm->isInheritanceTypeNone());
        $this->assertFalse($cm->isInheritanceTypeSingleCollection());
        $this->assertFalse($cm->isInheritanceTypeCollectionPerClass());
    }

    public function testLifecycleCallbackRegistration(): void
    {
        $cm = $this->chainNode();

        $this->assertFalse($cm->hasLifecycleCallbacks('prePersist'));
        $cm->addLifecycleCallback('setLabel', 'prePersist');
        $cm->addLifecycleCallback('setLabel', 'prePersist'); // duplicate ignored
        $this->assertTrue($cm->hasLifecycleCallbacks('prePersist'));
        $this->assertSame(['setLabel'], $cm->getLifecycleCallbacks('prePersist'));
        $this->assertSame([], $cm->getLifecycleCallbacks('postLoad'));

        $cm->setLifecycleCallbacks(['postLoad' => ['getId']]);
        $this->assertFalse($cm->hasLifecycleCallbacks('prePersist'));
        $this->assertTrue($cm->hasLifecycleCallbacks('postLoad'));
    }

    public function testInvokeLifecycleCallbackPassesArguments(): void
    {
        $cm = $this->chainNode();
        $cm->addLifecycleCallback('setLabel', 'prePersist');

        $node = new ChainNode();
        $cm->invokeLifecycleCallbacks('prePersist', $node, ['from-callback']);

        $this->assertSame('from-callback', $node->getLabel());
    }

    public function testInvokeLifecycleCallbackWithoutRegisteredCallbacksIsNoop(): void
    {
        $node = new ChainNode();
        $this->chainNode()->invokeLifecycleCallbacks('postLoad', $node);
        $this->addToAssertionCount(1);
    }

    public function testInvokeLifecycleCallbackRejectsForeignObject(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->chainNode()->invokeLifecycleCallbacks('prePersist', new \stdClass());
    }

    public function testFullyQualifiedClassName(): void
    {
        $cm = $this->chainNode();
        $namespace = 'Redking\\ParseBundle\\Tests\\Models\\Blog';

        $this->assertSame($namespace.'\\Foo', $cm->fullyQualifiedClassName('Foo'));
        $this->assertSame('Already\\Qualified', $cm->fullyQualifiedClassName('Already\\Qualified'));
    }

    public function testSetCustomRepositoryClassPrefixesNamespace(): void
    {
        $cm = $this->chainNode();

        $cm->setCustomRepositoryClass('NodeRepository');
        $this->assertSame(
            'Redking\\ParseBundle\\Tests\\Models\\Blog\\NodeRepository',
            $cm->customRepositoryClassName
        );
    }

    public function testGetLazyLoadKeysReturnsArrayWithoutWarning(): void
    {
        $this->assertSame([], $this->chainNode()->getLazyLoadKeys());
        $this->assertIsArray($this->om->getClassMetadata(Article::class)->getLazyLoadKeys());
        $this->assertIsArray($this->om->getClassMetadata(Tag::class)->getLazyLoadKeys());
    }

    public function testUnimplementedInterfaceStubsThrow(): void
    {
        $cm = $this->chainNode();

        try {
            $cm->isAssociationInverseSide('first');
            $this->fail('isAssociationInverseSide() is expected to be an unimplemented stub');
        } catch (\Exception $e) {
            $this->addToAssertionCount(1);
        }

        try {
            $cm->getAssociationMappedByTargetField('first');
            $this->fail('getAssociationMappedByTargetField() is expected to be an unimplemented stub');
        } catch (\Exception $e) {
            $this->addToAssertionCount(1);
        }
    }
}
