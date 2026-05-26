<?php

namespace Redking\ParseBundle\Tests\Functional;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\PersistentCollection;
use Redking\ParseBundle\Tests\Models\Blog\Tag;

/**
 * Coverage for the PersistentCollection wrapper API on an already-initialized
 * collection (no database round-trip): read delegation, dirty tracking,
 * snapshot/diff bookkeeping, ArrayAccess and clone semantics.
 */
class PersistentCollectionUnitTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [];

    private function newColl(array $elements, ?array $association = null): PersistentCollection
    {
        $class = $this->om->getClassMetadata(Tag::class);
        $coll = new PersistentCollection($this->om, $class, new ArrayCollection($elements));
        $coll->setInitialized(true);

        if (null !== $association) {
            $coll->setOwner(new Tag(), $association);
        }

        return $coll;
    }

    private function manyAssociation(): array
    {
        return [
            'inversedBy' => null,
            'mappedBy' => 'owner',
            'type' => ClassMetadata::MANY,
            'fetch' => ClassMetadata::FETCH_LAZY,
            'orphanRemoval' => false,
            'isOwningSide' => false,
            'targetDocument' => Tag::class,
        ];
    }

    public function testReadDelegation(): void
    {
        $coll = $this->newColl(['a', 'b', 'c']);

        $this->assertCount(3, $coll);
        $this->assertSame('a', $coll->first());
        $this->assertSame('c', $coll->last());
        $this->assertFalse($coll->isEmpty());
        $this->assertSame(['a', 'b', 'c'], $coll->toArray());
        $this->assertSame([0, 1, 2], $coll->getKeys());
        $this->assertSame(['a', 'b', 'c'], $coll->getValues());
        $this->assertTrue($coll->contains('b'));
        $this->assertFalse($coll->contains('z'));
        $this->assertTrue($coll->containsKey(1));
        $this->assertSame(2, $coll->indexOf('c'));
        $this->assertSame('a', $coll->get(0));
    }

    public function testHigherOrderDelegation(): void
    {
        $coll = $this->newColl(['a', 'b', 'c']);

        $this->assertTrue($coll->exists(fn ($k, $v) => 'b' === $v));
        $this->assertTrue($coll->forAll(fn ($k, $v) => is_string($v)));

        $mapped = $coll->map(fn ($v) => strtoupper($v));
        $this->assertInstanceOf(Collection::class, $mapped);
        $this->assertSame(['A', 'B', 'C'], $mapped->getValues());

        $filtered = $coll->filter(fn ($v) => 'b' !== $v);
        $this->assertSame(['a', 'c'], $filtered->getValues());

        [$match, $noMatch] = $coll->partition(fn ($k, $v) => 'a' === $v);
        $this->assertSame(['a'], $match->getValues());
        $this->assertSame(['b', 'c'], $noMatch->getValues());

        $this->assertSame([1 => 'b'], $coll->slice(1, 1));
        $this->assertSame('b', $coll->findFirst(fn ($k, $v) => 'b' === $v));
        $this->assertSame('abc', $coll->reduce(fn ($carry, $v) => $carry.$v, ''));
    }

    public function testIteration(): void
    {
        $coll = $this->newColl(['a', 'b']);

        $this->assertSame('a', $coll->current());
        $this->assertSame('b', $coll->next());
        $this->assertSame(1, $coll->key());

        $seen = [];
        foreach ($coll as $value) {
            $seen[] = $value;
        }
        $this->assertSame(['a', 'b'], $seen);
    }

    public function testAddMarksDirty(): void
    {
        $coll = $this->newColl(['a']);
        $this->assertFalse($coll->isDirty());

        $this->assertTrue($coll->add('b'));

        $this->assertTrue($coll->isDirty());
        $this->assertSame(['a', 'b'], $coll->toArray());

        $coll->setDirty(false);
        $this->assertFalse($coll->isDirty());
    }

    public function testSetRemoveAndRemoveElement(): void
    {
        $coll = $this->newColl(['a', 'b', 'c'], $this->manyAssociation());

        $coll->set(1, 'B');
        $this->assertSame('B', $coll->get(1));
        $this->assertTrue($coll->isDirty());

        $this->assertSame('a', $coll->remove(0));
        $this->assertFalse($coll->containsKey(0));

        $this->assertTrue($coll->removeElement('c'));
        $this->assertFalse($coll->contains('c'));
        $this->assertFalse($coll->removeElement('not-present'));
    }

    public function testArrayAccess(): void
    {
        $coll = $this->newColl(['a', 'b'], $this->manyAssociation());

        $this->assertTrue(isset($coll[0]));
        $this->assertSame('a', $coll[0]);

        $coll[] = 'c';
        $this->assertSame('c', $coll[2]);

        $coll[0] = 'A';
        $this->assertSame('A', $coll[0]);

        unset($coll[1]);
        $this->assertFalse(isset($coll[1]));
    }

    public function testSnapshotAndDiffs(): void
    {
        $coll = $this->newColl(['a', 'b']);
        $coll->takeSnapshot();
        $this->assertSame(['a', 'b'], $coll->getSnapshot());

        $coll->add('c');
        $coll->removeElement('a');

        $this->assertContains('c', $coll->getInsertDiff());
        $this->assertContains('a', $coll->getDeleteDiff());
    }

    public function testClearEmptiesAndInitializes(): void
    {
        $coll = $this->newColl(['a', 'b'], $this->manyAssociation());

        $coll->clear();

        $this->assertTrue($coll->isEmpty());
        $this->assertTrue($coll->isInitialized());
    }

    public function testAccessorsAndFlags(): void
    {
        $assoc = $this->manyAssociation();
        $owner = new Tag();
        $class = $this->om->getClassMetadata(Tag::class);
        $inner = new ArrayCollection(['x']);
        $coll = new PersistentCollection($this->om, $class, $inner);
        $coll->setOwner($owner, $assoc);

        $this->assertSame($owner, $coll->getOwner());
        $this->assertSame($assoc, $coll->getMapping());
        $this->assertSame($class, $coll->getTypeClass());
        $this->assertSame($inner, $coll->unwrap());

        $coll->setInitialized(false);
        $this->assertFalse($coll->isInitialized());
        $coll->setInitialized(true);
        $this->assertTrue($coll->isInitialized());
    }

    public function testCloneDetachesOwnerAndResetsState(): void
    {
        $coll = $this->newColl(['a', 'b'], $this->manyAssociation());
        $coll->takeSnapshot();

        $clone = clone $coll;

        $this->assertNull($clone->getOwner());
        $this->assertSame([], $clone->getSnapshot());
        $this->assertTrue($clone->isDirty());
        $this->assertSame(['a', 'b'], $clone->toArray());
        // The clone wraps a distinct inner collection.
        $this->assertNotSame($coll->unwrap(), $clone->unwrap());
    }
}
