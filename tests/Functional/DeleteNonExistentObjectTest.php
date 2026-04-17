<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseObject;
use Parse\ParseQuery;
use Redking\ParseBundle\Tests\Models\Blog\Picture;
use Redking\ParseBundle\Tests\Models\Blog\User;

/**
 * Reproduces the "Object not found" (code 101) error during bulk deletion.
 *
 * Scenario: an object is loaded in the ORM identity map then deleted directly
 * in Parse (bypassing the ORM) before the ORM flush runs. This simulates
 * any idempotent delete scenario where objects referenced by the ORM are
 * already gone (stale references, concurrent deletion, etc.).
 *
 * Without the fix, ObjectPersister::executeDeletions() calls ParseObject::destroyAll()
 * which throws ParseAggregateException with code 101 for every missing object,
 * which bubbles up as a WrappedParseException and aborts the whole cleaner.
 *
 * With the fix, code-101 errors are silently ignored (the object is already gone,
 * the end result is the same: it no longer exists).
 */
class DeleteNonExistentObjectTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        User::class,
        Picture::class,
    ];

    /**
     * Deleting a single object that was already removed from Parse must not throw.
     */
    public function testFlushRemoveOnAlreadyDeletedObjectDoesNotThrow(): void
    {
        // 1. Create and persist a Picture
        $picture = new Picture();
        $picture->setFile('ghost.jpg');
        $this->om->persist($picture);
        $this->om->flush();
        $pictureId = $picture->getId();
        $this->assertNotNull($pictureId);

        // 2. Reload it so the ORM has full originalObjectData
        $this->om->clear();
        $picture = $this->om->getRepository(Picture::class)->find($pictureId);
        $this->assertNotNull($picture);

        // 3. Delete the underlying Parse record DIRECTLY, bypassing the ORM
        //    (simulates a stale reference: object is gone before the next flush)
        $collection = $this->om->getClassMetadata(Picture::class)->getCollection();
        $parseQuery = new ParseQuery($collection);
        $raw = $parseQuery->equalTo('objectId', $pictureId)->first(true);
        $raw->destroy(true);

        // 4. Mark for deletion via ORM and flush
        //    WITHOUT the fix this throws: "Errors during batch destroy. [101] Object not found."
        $this->om->remove($picture);
        $this->om->flush(); // must NOT throw
    }

    /**
     * Deleting multiple objects where some were already removed must not throw,
     * and the ones that existed must actually be deleted.
     */
    public function testFlushRemoveMixedExistingAndAlreadyDeletedDoesNotThrow(): void
    {
        // Create 3 pictures
        $pictures = [];
        for ($i = 0; $i < 3; $i++) {
            $p = new Picture();
            $p->setFile("mixed_{$i}.jpg");
            $this->om->persist($p);
            $pictures[] = $p;
        }
        $this->om->flush();
        $ids = array_map(fn($p) => $p->getId(), $pictures);
        $this->assertCount(3, array_filter($ids));

        // Reload all
        $this->om->clear();
        $repo = $this->om->getRepository(Picture::class);
        $loaded = [];
        foreach ($ids as $id) {
            $loaded[] = $repo->find($id);
        }

        // Directly destroy the first one from Parse
        $collection = $this->om->getClassMetadata(Picture::class)->getCollection();
        $parseQuery = new ParseQuery($collection);
        $raw = $parseQuery->equalTo('objectId', $ids[0])->first(true);
        $raw->destroy(true);

        // Remove all 3 via ORM (one is already gone in Parse)
        foreach ($loaded as $p) {
            $this->om->remove($p);
        }
        $this->om->flush(); // must NOT throw

        // The 2 remaining ones must actually be gone
        $this->om->clear();
        $remaining = $repo->findAll();
        $this->assertEmpty($remaining, 'All 3 pictures should be gone after flush');
    }
}
