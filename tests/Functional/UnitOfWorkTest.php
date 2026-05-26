<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseACL;
use Parse\ParseObject;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\Persisters\CollectionArrayPersister;
use Redking\ParseBundle\Persisters\CollectionRelationPersister;
use Redking\ParseBundle\Persisters\ObjectPersister;
use Redking\ParseBundle\Tests\Models\Blog\ChainNode;
use Redking\ParseBundle\Tests\Models\Blog\Tag;
use Redking\ParseBundle\UnitOfWork;

/**
 * Direct coverage for UnitOfWork public API: object state machine, identity map,
 * identifier accessors, scheduling introspection, ACL building, raw changeset
 * computation, persister caching and targeted clear/detach.
 */
class UnitOfWorkTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        ChainNode::class,
        Tag::class,
    ];

    private function persistNode(string $label): ChainNode
    {
        $node = new ChainNode();
        $node->setLabel($label);
        $this->om->persist($node);
        $this->om->flush();

        return $node;
    }

    // --- state machine -------------------------------------------------------

    public function testObjectStateNewThenManaged(): void
    {
        $node = new ChainNode();
        $this->assertSame(UnitOfWork::STATE_NEW, $this->uow->getObjectState($node));

        $node->setLabel('s');
        $this->om->persist($node);
        $this->om->flush();

        $this->assertSame(UnitOfWork::STATE_MANAGED, $this->uow->getObjectState($node));
    }

    public function testObjectStateDetachedAfterClear(): void
    {
        $node = $this->persistNode('detached');
        $this->om->clear();

        // id is set, not in identity map, but exists in DB => DETACHED.
        $this->assertSame(UnitOfWork::STATE_DETACHED, $this->uow->getObjectState($node));
    }

    public function testObjectStateRemoved(): void
    {
        $node = $this->persistNode('removed');

        $this->om->remove($node);

        $this->assertSame(UnitOfWork::STATE_REMOVED, $this->uow->getObjectState($node));
        $this->assertTrue($this->uow->isScheduledForDelete($node));
    }

    // --- identity map --------------------------------------------------------

    public function testIdentityMapAndTryGetById(): void
    {
        $node = $this->persistNode('idmap');
        $id = $node->getId();

        $this->assertTrue($this->uow->isInIdentityMap($node));
        $this->assertSame($node, $this->uow->tryGetById($id, ChainNode::class));
        $this->assertArrayHasKey(ChainNode::class, $this->uow->getIdentityMap());

        $this->om->clear();
        $this->assertFalse($this->uow->isInIdentityMap($node));
        $this->assertFalse($this->uow->tryGetById($id, ChainNode::class));
    }

    public function testRemoveFromIdentityMap(): void
    {
        $node = $this->persistNode('rmidmap');

        $this->assertTrue($this->uow->removeFromIdentityMap($node));
        $this->assertFalse($this->uow->isInIdentityMap($node));
    }

    public function testGetManagedObjectFromParseObject(): void
    {
        $node = $this->persistNode('managed');
        $parseObject = $this->uow->getOriginalObjectData($node);

        $this->assertSame($node, $this->uow->getManagedObjectFromParseObject($parseObject));

        $unknown = new ParseObject('blog_chain_node');
        $this->assertNull($this->uow->getManagedObjectFromParseObject($unknown));
    }

    // --- original data / identifiers ----------------------------------------

    public function testGetOriginalObjectData(): void
    {
        $node = $this->persistNode('orig');

        $parseObject = $this->uow->getOriginalObjectData($node);
        $this->assertInstanceOf(ParseObject::class, $parseObject);
        $this->assertSame($parseObject, $this->uow->getOriginalObjectDataByOid(spl_object_hash($node)));

        $this->assertNull($this->uow->getOriginalObjectData(new ChainNode()));
    }

    public function testIdentifierAccessors(): void
    {
        $node = $this->persistNode('ident');
        $id = $node->getId();

        $this->assertSame($id, $this->uow->getObjectIdentifier($node));
        $this->assertSame($id, $this->uow->getEntityIdentifier($node));
        $this->assertSame($id, $this->uow->getDocumentIdentifier($node));

        $this->assertNull($this->uow->getObjectIdentifier(new ChainNode()));
    }

    // --- scheduling ----------------------------------------------------------

    public function testScheduleForInsert(): void
    {
        $node = new ChainNode();
        $node->setLabel('queued');
        $this->om->persist($node);

        $this->assertTrue($this->uow->isScheduledForInsert($node));
        $this->assertContains($node, $this->uow->getScheduleForInsert());
    }

    // --- clear / detach ------------------------------------------------------

    public function testClearWithObjectNameDetachesOnlyThatType(): void
    {
        $node = $this->persistNode('node');

        $tag = new Tag();
        $tag->setName('tag');
        $this->om->persist($tag);
        $this->om->flush();

        $this->uow->clear(ChainNode::class);
        $this->assertFalse($this->uow->isInIdentityMap($node), 'ChainNode must be detached');
        $this->assertTrue($this->uow->isInIdentityMap($tag), 'Tag must remain managed');

        $this->uow->clear();
        $this->assertFalse($this->uow->isInIdentityMap($tag), 'clear() detaches everything');
    }

    public function testDetach(): void
    {
        $node = $this->persistNode('toDetach');

        $this->om->detach($node);

        $this->assertFalse($this->om->contains($node));
        $this->assertFalse($this->uow->isInIdentityMap($node));
    }

    // --- ACL -----------------------------------------------------------------

    public function testGetAclDefaultsToPublicReadWrite(): void
    {
        $acl = $this->uow->getAcl(new ChainNode());

        $this->assertInstanceOf(ParseACL::class, $acl);
        $this->assertTrue($acl->getPublicReadAccess());
        $this->assertTrue($acl->getPublicWriteAccess());
    }

    public function testGetAclWithRoleAccess(): void
    {
        $node = new ChainNode();
        $node->addRoleAcl('admin', true, false);

        $acl = $this->uow->getAcl($node);

        $this->assertTrue($acl->getRoleReadAccessWithName('admin'));
        $this->assertFalse($acl->getRoleWriteAccessWithName('admin'));
    }

    public function testApplyAclSetsAclOnOriginalParseObject(): void
    {
        $node = $this->persistNode('aclTarget');
        $node->setPublicAcl(true, false);

        $this->uow->applyAcl($node);

        $this->assertInstanceOf(ParseACL::class, $this->uow->getOriginalObjectData($node)->getAcl());
    }

    // --- raw changeset -------------------------------------------------------

    public function testGetChangesetFromParseObjectsDetectsScalarChange(): void
    {
        $class = $this->om->getClassMetadata(ChainNode::class);

        $original = new ParseObject('blog_chain_node');
        $original->set('label', 'before');
        $actual = new ParseObject('blog_chain_node');
        $actual->set('label', 'after');

        $changeSet = $this->uow->getChangesetFromParseObjects($class, $actual, $original);

        $this->assertSame(['label' => ['before', 'after']], $changeSet);
    }

    public function testGetChangesetFromParseObjectsReturnsEmptyWhenUnchanged(): void
    {
        $class = $this->om->getClassMetadata(ChainNode::class);

        $original = new ParseObject('blog_chain_node');
        $original->set('label', 'same');
        $actual = new ParseObject('blog_chain_node');
        $actual->set('label', 'same');

        $this->assertSame([], $this->uow->getChangesetFromParseObjects($class, $actual, $original));
    }

    // --- persisters ----------------------------------------------------------

    public function testObjectPersisterIsCached(): void
    {
        $first = $this->uow->getObjectPersister(ChainNode::class);
        $second = $this->uow->getObjectPersister(ChainNode::class);

        $this->assertInstanceOf(ObjectPersister::class, $first);
        $this->assertSame($first, $second);
    }

    public function testCollectionPersisterByImplementation(): void
    {
        $array = $this->uow->getCollectionPersister(['implementation' => ClassMetadata::ASSOCIATION_IMPL_ARRAY]);
        $relation = $this->uow->getCollectionPersister(['implementation' => ClassMetadata::ASSOCIATION_IMPL_RELATION]);

        $this->assertInstanceOf(CollectionArrayPersister::class, $array);
        $this->assertInstanceOf(CollectionRelationPersister::class, $relation);
        // Cached per implementation.
        $this->assertSame($array, $this->uow->getCollectionPersister(['implementation' => ClassMetadata::ASSOCIATION_IMPL_ARRAY]));
    }
}
