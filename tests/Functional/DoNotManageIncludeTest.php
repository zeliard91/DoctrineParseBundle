<?php

namespace Redking\ParseBundle\Tests\Functional;

use Redking\ParseBundle\Tests\Models\Blog\Article;
use Redking\ParseBundle\Tests\Models\Blog\ChainNode;
use Redking\ParseBundle\Tests\Models\Blog\Tag;

/**
 * Coverage for the 'doctrine.do_not_manage' query hint combined with includeKey().
 *
 * Production data-loss bug: the hydrator pre-registered a concrete instance for
 * every fully-available association before the "load associations" loop ran.
 * Under do_not_manage, getOrCreateObject() returns that pre-registered instance
 * early — before hydrate() runs — so the included object stayed EMPTY (all
 * properties null) yet kept the full original ParseObject as its change-detection
 * baseline. The next unrelated flush then computed a "full -> null" changeset and
 * wiped every field of the included object in the database.
 *
 * do_not_manage contract being asserted here:
 *  - included references (ReferenceOne and ReferenceMany) are fully hydrated;
 *  - the whole returned graph is DETACHED (never tracked by the UnitOfWork);
 *  - an unrelated flush never schedules — let alone wipes — those objects;
 *  - an object that was ALREADY managed before the query is neither detached
 *    nor wiped (the identity-map-hit early-return must leave it untouched).
 */
class DoNotManageIncludeTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        ChainNode::class,
        Article::class,
        Tag::class,
    ];

    /**
     * Core regression: a ReferenceOne loaded via includeKey under do_not_manage
     * must be hydrated, detached, and must NOT be wiped by a later flush.
     */
    public function testReferenceOneIncludeIsHydratedDetachedAndNotWiped(): void
    {
        $child = new ChainNode();
        $child->setLabel('child-value');
        $this->om->persist($child);

        $root = new ChainNode();
        $root->setLabel('root');
        $root->setFirst($child);
        $this->om->persist($root);

        $this->om->flush();
        $rootId = $root->getId();
        $childId = $child->getId();
        $this->om->clear();

        // Mirrors AIUsageChecker: includeKey on a ReferenceOne + do_not_manage.
        $loadedRoot = $this->om->createQueryBuilder(ChainNode::class)
            ->field('id')->equals($rootId)
            ->includeKey('first')
            ->getQuery()
            ->setHints(['doctrine.do_not_manage' => true])
            ->getSingleResult();

        $this->assertNotNull($loadedRoot);

        // Hydrated (not an empty instance) ...
        $this->assertNotNull($loadedRoot->getFirst());
        $this->assertSame('child-value', $loadedRoot->getFirst()->getLabel());

        // ... and the whole graph is detached (read-only).
        $this->assertFalse($this->om->contains($loadedRoot), 'top-level object must be detached');
        $this->assertFalse($this->om->contains($loadedRoot->getFirst()), 'included reference must be detached');

        // An unrelated flush must not wipe the referenced object.
        $this->om->flush();
        $this->om->clear();

        $reloadedChild = $this->om->createQueryBuilder(ChainNode::class)
            ->field('id')->equals($childId)
            ->getQuery()
            ->getSingleResult();

        $this->assertNotNull($reloadedChild);
        $this->assertSame('child-value', $reloadedChild->getLabel(), 'referenced object must not be wiped');
    }

    /**
     * A detached included reference must be inert: mutating it and flushing is a
     * no-op (Doctrine ignores detached objects), so the DB stays untouched.
     */
    public function testMutatingDetachedIncludeIsANoOp(): void
    {
        $child = new ChainNode();
        $child->setLabel('original');
        $this->om->persist($child);

        $root = new ChainNode();
        $root->setLabel('root');
        $root->setFirst($child);
        $this->om->persist($root);

        $this->om->flush();
        $rootId = $root->getId();
        $childId = $child->getId();
        $this->om->clear();

        $loadedRoot = $this->om->createQueryBuilder(ChainNode::class)
            ->field('id')->equals($rootId)
            ->includeKey('first')
            ->getQuery()
            ->setHints(['doctrine.do_not_manage' => true])
            ->getSingleResult();

        // Mutate the detached reference, then flush.
        $loadedRoot->getFirst()->setLabel('tampered');
        $this->om->flush();
        $this->om->clear();

        $reloadedChild = $this->om->createQueryBuilder(ChainNode::class)
            ->field('id')->equals($childId)
            ->getQuery()
            ->getSingleResult();

        $this->assertSame('original', $reloadedChild->getLabel(), 'mutation on a detached include must not persist');
    }

    /**
     * Same wipe-safety guarantee for a ReferenceMany include (owning side, array
     * of pointers): the owner is detached and an unrelated flush never wipes the
     * collection elements (each element is detached individually during
     * hydration, so it is never scheduled for update).
     *
     * NB: we deliberately do not iterate the collection on the detached owner —
     * an included collection is not flagged initialized, so traversing it on a
     * detached owner triggers a lazy reload with no managed context. That is a
     * pre-existing usability limitation, unrelated to the data-loss bug under
     * test here.
     */
    public function testReferenceManyIncludeOwnerIsDetachedAndElementsNotWiped(): void
    {
        $tag1 = new Tag();
        $tag1->setName('tag-one');
        $this->om->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('tag-two');
        $this->om->persist($tag2);

        $article = new Article();
        $article->setTitle('Article');
        $article->addTag($tag1);
        $article->addTag($tag2);
        $this->om->persist($article);

        $this->om->flush();
        $articleId = $article->getId();
        $tag1Id = $tag1->getId();
        $tag2Id = $tag2->getId();
        $this->om->clear();

        $loadedArticle = $this->om->createQueryBuilder(Article::class)
            ->field('id')->equals($articleId)
            ->includeKey('tags')
            ->getQuery()
            ->setHints(['doctrine.do_not_manage' => true])
            ->getSingleResult();

        $this->assertNotNull($loadedArticle);
        $this->assertFalse($this->om->contains($loadedArticle), 'owner must be detached');

        $this->om->flush();
        $this->om->clear();

        foreach ([$tag1Id => 'tag-one', $tag2Id => 'tag-two'] as $id => $expected) {
            $reloaded = $this->om->createQueryBuilder(Tag::class)
                ->field('id')->equals($id)
                ->getQuery()
                ->getSingleResult();
            $this->assertNotNull($reloaded);
            $this->assertSame($expected, $reloaded->getName(), 'collection element must not be wiped');
        }
    }

    /**
     * An included ReferenceMany must be usable (iterable) on the detached owner
     * returned by a do_not_manage query: the included payload makes the
     * collection initialized, so traversing it must not attempt a lazy reload —
     * which, on a detached owner, has no original data and fatally fails
     * ("Call to a member function get() on null" in loadReferenceManyCollectionOwningSide).
     */
    public function testIncludedCollectionIsIterableOnDetachedOwner(): void
    {
        $tag1 = new Tag();
        $tag1->setName('tag-one');
        $this->om->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('tag-two');
        $this->om->persist($tag2);

        $article = new Article();
        $article->setTitle('Article');
        $article->addTag($tag1);
        $article->addTag($tag2);
        $this->om->persist($article);

        $this->om->flush();
        $articleId = $article->getId();
        $this->om->clear();

        $loadedArticle = $this->om->createQueryBuilder(Article::class)
            ->field('id')->equals($articleId)
            ->includeKey('tags')
            ->getQuery()
            ->setHints(['doctrine.do_not_manage' => true])
            ->getSingleResult();

        $this->assertFalse($this->om->contains($loadedArticle), 'owner must be detached');

        $names = [];
        foreach ($loadedArticle->getTags() as $tag) {
            $names[] = $tag->getName();
        }
        sort($names);
        $this->assertSame(['tag-one', 'tag-two'], $names, 'included collection must be iterable without a lazy reload');
    }

    /**
     * An object already MANAGED before a do_not_manage query is included by must
     * stay managed and untouched: the identity-map-hit early-return must return
     * the existing instance as-is (neither detach nor wipe it).
     */
    public function testAlreadyManagedReferenceIsNeitherDetachedNorWiped(): void
    {
        $child = new ChainNode();
        $child->setLabel('keep');
        $this->om->persist($child);

        $root = new ChainNode();
        $root->setLabel('root');
        $root->setFirst($child);
        $this->om->persist($root);

        $this->om->flush();
        $rootId = $root->getId();
        $childId = $child->getId();
        $this->om->clear();

        // Legitimately load & manage the child first.
        $managedChild = $this->om->createQueryBuilder(ChainNode::class)
            ->field('id')->equals($childId)
            ->getQuery()
            ->getSingleResult();
        $this->assertTrue($this->om->contains($managedChild));

        // A do_not_manage query that includes the same child must reuse it.
        $loadedRoot = $this->om->createQueryBuilder(ChainNode::class)
            ->field('id')->equals($rootId)
            ->includeKey('first')
            ->getQuery()
            ->setHints(['doctrine.do_not_manage' => true])
            ->getSingleResult();

        $this->assertSame($managedChild, $loadedRoot->getFirst(), 'identity must be preserved');
        $this->assertTrue($this->om->contains($managedChild), 'a pre-managed object must not be detached by do_not_manage');

        $this->om->flush();
        $this->om->clear();

        $reloadedChild = $this->om->createQueryBuilder(ChainNode::class)
            ->field('id')->equals($childId)
            ->getQuery()
            ->getSingleResult();
        $this->assertSame('keep', $reloadedChild->getLabel(), 'a pre-managed object must not be wiped');
    }
}
