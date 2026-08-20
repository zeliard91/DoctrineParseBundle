<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseGeoPoint;
use Redking\ParseBundle\Tests\Models\Blog\Address;
use Redking\ParseBundle\Tests\Models\Blog\Article;
use Redking\ParseBundle\Tests\Models\Blog\ChainNode;
use Redking\ParseBundle\Tests\Models\Blog\Picture;
use Redking\ParseBundle\Tests\Models\Blog\Post;
use Redking\ParseBundle\Tests\Models\Blog\SecureDocument;
use Redking\ParseBundle\Tests\Models\Blog\Tag;
use Redking\ParseBundle\Tests\Models\Blog\User;

/**
 * A query that returns an object which is already managed must not silently revert
 * the changes made to that object since it was loaded.
 *
 * UnitOfWork::getOrCreateObject() used to hydrate on every identity map hit, so any
 * lookup returning an already managed object reset its properties to the server
 * state. The symptom is a change that simply never reaches Parse: typically a
 * listener that resets a pointer, then a second lookup of the same commit that puts
 * the old pointer back.
 *
 * The counterpart is that re-hydration is legitimately used to complete a managed
 * object with the payload of an includeKey(), and to fill the empty placeholders the
 * hydrator pre-registers for fully included associations. Those must keep working,
 * hence the "still hydrates" group at the end.
 */
class ManagedObjectRehydrationTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        User::class,
        Post::class,
        Article::class,
        Tag::class,
        ChainNode::class,
        Address::class,
        Picture::class,
        SecureDocument::class,
    ];

    // --- a lookup must not revert local changes --------------------------------

    public function testScalarChangeSurvivesALookupOfTheSameObject(): void
    {
        $post = $this->newPost('original');
        $id = $post->getId();
        $this->om->clear();

        $post = $this->om->getRepository(Post::class)->find($id);
        $post->setText('modified');

        // Any query returning $post again used to reset text to 'original'.
        $this->om->createQueryBuilder(Post::class)->field('id')->equals($id)->getQuery()->getSingleResult();

        $this->assertSame('modified', $post->getText(), 'The in-memory change must survive the lookup');

        $this->om->flush();
        $this->om->clear();

        $this->assertSame('modified', $this->om->getRepository(Post::class)->find($id)->getText());
    }

    public function testPointerResetToNullSurvivesALookupOfTheSameObject(): void
    {
        [$rootId] = $this->newChain();
        $this->om->clear();

        $root = $this->om->getRepository(ChainNode::class)->find($rootId);
        $root->setFirst(null);

        $this->om->createQueryBuilder(ChainNode::class)->field('id')->equals($rootId)->getQuery()->getSingleResult();

        $this->assertNull($root->getFirst(), 'The reset pointer must stay null');

        $this->om->flush();
        $this->om->clear();

        $this->assertNull($this->om->getRepository(ChainNode::class)->find($rootId)->getFirst());
    }

    public function testPointerReassignedSurvivesALookupOfTheSameObject(): void
    {
        [$rootId, , $bId] = $this->newChain();
        $this->om->clear();

        $root = $this->om->getRepository(ChainNode::class)->find($rootId);
        $b = $this->om->getRepository(ChainNode::class)->find($bId);
        $root->setFirst($b);

        $this->om->createQueryBuilder(ChainNode::class)->field('id')->equals($rootId)->getQuery()->getSingleResult();

        $this->assertSame($bId, $root->getFirst()->getId(), 'The reassigned pointer must be kept');

        $this->om->flush();
        $this->om->clear();

        $this->assertSame($bId, $this->om->getRepository(ChainNode::class)->find($rootId)->getFirst()->getId());
    }

    /**
     * The exact shape of the production incident: two relations of the same class
     * reference the removed object, so the cleanup issues two lookups and the second
     * one used to undo what the first had just changed.
     */
    public function testTwoSuccessiveLookupsOnTheSameObjectKeepBothChanges(): void
    {
        [$rootId] = $this->newChain();
        $this->om->clear();

        $root = $this->om->getRepository(ChainNode::class)->find($rootId);

        $root->setFirst(null);
        $this->om->createQueryBuilder(ChainNode::class)->field('id')->equals($rootId)->getQuery()->getSingleResult();

        $root->setSecond(null);
        $this->om->createQueryBuilder(ChainNode::class)->field('id')->equals($rootId)->getQuery()->getSingleResult();

        $this->assertNull($root->getFirst(), 'first must still be null after the second lookup');
        $this->assertNull($root->getSecond());

        $this->om->flush();
        $this->om->clear();

        $reloaded = $this->om->getRepository(ChainNode::class)->find($rootId);
        $this->assertNull($reloaded->getFirst(), 'first must have been persisted as null');
        $this->assertNull($reloaded->getSecond());
    }

    /**
     * Same as above but with the change folded into the unit of work first, which is
     * what a listener does when it calls recomputeSingleObjectChangeSet().
     */
    public function testChangeAlreadyComputedSurvivesALookupOfTheSameObject(): void
    {
        [$rootId] = $this->newChain();
        $this->om->clear();

        $root = $this->om->getRepository(ChainNode::class)->find($rootId);
        $root->setFirst(null);
        $this->uow->recomputeSingleObjectChangeSet($this->om->getClassMetadata(ChainNode::class), $root);

        $this->om->createQueryBuilder(ChainNode::class)->field('id')->equals($rootId)->getQuery()->getSingleResult();

        $this->om->flush();
        $this->om->clear();

        $this->assertNull($this->om->getRepository(ChainNode::class)->find($rootId)->getFirst());
    }

    public function testCollectionRemovalSurvivesALookupOfTheOwner(): void
    {
        [$articleId, $keptId] = $this->newArticleWithTags();
        $this->om->clear();

        $article = $this->om->getRepository(Article::class)->find($articleId);
        $this->assertCount(2, $article->getTags());

        foreach ($article->getTags()->toArray() as $tag) {
            if ($tag->getId() !== $keptId) {
                $article->getTags()->removeElement($tag);
            }
        }
        $this->assertCount(1, $article->getTags());

        $this->om->createQueryBuilder(Article::class)->field('id')->equals($articleId)->getQuery()->getSingleResult();

        $this->assertCount(1, $article->getTags(), 'The removal must survive the lookup');

        $this->om->flush();
        $this->om->clear();

        $reloaded = $this->om->getRepository(Article::class)->find($articleId);
        $this->assertSame([$keptId], array_map(fn (Tag $t) => $t->getId(), $reloaded->getTags()->toArray()));
    }

    public function testCollectionAdditionSurvivesALookupOfTheOwner(): void
    {
        [$articleId] = $this->newArticleWithTags();

        $extra = new Tag();
        $extra->setName('extra');
        $this->om->persist($extra);
        $this->om->flush();
        $extraId = $extra->getId();
        $this->om->clear();

        $article = $this->om->getRepository(Article::class)->find($articleId);
        $article->getTags()->add($this->om->getRepository(Tag::class)->find($extraId));

        $this->om->createQueryBuilder(Article::class)->field('id')->equals($articleId)->getQuery()->getSingleResult();

        $this->assertCount(3, $article->getTags(), 'The addition must survive the lookup');

        $this->om->flush();
        $this->om->clear();

        $this->assertCount(3, $this->om->getRepository(Article::class)->find($articleId)->getTags());
    }

    public function testChangeSurvivesALookupThatReturnsManyObjects(): void
    {
        $first = $this->newPost('first');
        $second = $this->newPost('second');
        $firstId = $first->getId();
        $secondId = $second->getId();
        $this->om->clear();

        $first = $this->om->getRepository(Post::class)->find($firstId);
        $first->setText('modified');

        // A broad query, not a lookup by id : the object comes back among others.
        $this->om->getRepository(Post::class)->findAll();

        $this->assertSame('modified', $first->getText());

        $this->om->flush();
        $this->om->clear();

        $this->assertSame('modified', $this->om->getRepository(Post::class)->find($firstId)->getText());
        $this->assertSame('second', $this->om->getRepository(Post::class)->find($secondId)->getText());
    }

    public function testChangeOnOneObjectDoesNotFreezeTheOthers(): void
    {
        $modified = $this->newPost('one');
        $untouched = $this->newPost('two');
        $modifiedId = $modified->getId();
        $untouchedId = $untouched->getId();
        $this->om->clear();

        $modified = $this->om->getRepository(Post::class)->find($modifiedId);
        $untouched = $this->om->getRepository(Post::class)->find($untouchedId);
        $modified->setText('changed');

        // Simulate the row of the untouched object changing server side, then a lookup.
        $this->rawUpdate('blog_post', $untouchedId, ['text' => 'server side']);
        $this->om->getRepository(Post::class)->findAll();

        $this->assertSame('changed', $modified->getText(), 'the modified object keeps its change');
        $this->assertSame(
            'server side',
            $untouched->getText(),
            'an object with no local change is still refreshed by a lookup'
        );
    }

    // --- explicit refresh still wins -------------------------------------------

    public function testRefreshDiscardsLocalChanges(): void
    {
        $post = $this->newPost('original');
        $id = $post->getId();
        $this->om->clear();

        $post = $this->om->getRepository(Post::class)->find($id);
        $post->setText('modified');

        $this->om->refresh($post);

        $this->assertSame('original', $post->getText(), 'refresh() is an explicit request to drop local changes');
    }

    /**
     * MyBundle\Service\VisiteMedicaleWorkflow::assignActionToManager() refreshes an
     * object and each object it points to, on purpose, to drop the changes made before.
     * MyBundle\Service\N8NGateWay does the same to make sure an invalid visit can not
     * be flushed. Both must keep working exactly as before.
     */
    public function testRefreshDiscardsAModifiedPointer(): void
    {
        [$rootId, $aId] = $this->newChain();
        $this->om->clear();

        $root = $this->om->getRepository(ChainNode::class)->find($rootId);
        $root->setFirst(null);
        $root->setLabel('modified');

        $this->om->refresh($root);

        $this->assertSame('root', $root->getLabel(), 'the scalar is back to the server state');
        $this->assertNotNull($root->getFirst(), 'the pointer is back too');
        $this->assertSame($aId, $root->getFirst()->getId());

        // And nothing is left to flush.
        $this->om->flush();
        $this->om->clear();
        $this->assertSame($aId, $this->om->getRepository(ChainNode::class)->find($rootId)->getFirst()->getId());
    }

    public function testRefreshDiscardsAModifiedCollection(): void
    {
        [$articleId] = $this->newArticleWithTags();
        $this->om->clear();

        $article = $this->om->getRepository(Article::class)->find($articleId);
        $article->getTags()->clear();
        $this->assertCount(0, $article->getTags());

        $this->om->refresh($article);

        $this->assertCount(2, $article->getTags(), 'the collection is back to the server state');
    }

    /**
     * The workflow refreshes objects reached through a pointer, which may still be an
     * uninitialized proxy at that point.
     */
    public function testRefreshOfAPointedObjectAndOfAProxy(): void
    {
        [$rootId, $aId] = $this->newChain();
        $this->om->clear();

        $root = $this->om->getRepository(ChainNode::class)->find($rootId);
        $pointed = $root->getFirst();
        $this->assertTrue($this->om->isUninitializedObject($pointed), 'reached through a pointer, still lazy');

        $this->om->refresh($pointed);

        $this->assertFalse($this->om->isUninitializedObject($pointed));
        $this->assertSame('A', $pointed->getLabel());

        $pointed->setLabel('modified');
        $this->om->refresh($pointed);
        $this->assertSame('A', $pointed->getLabel(), 'refresh drops the change on a pointed object too');
    }

    /**
     * The interaction that matters: an object whose change was protected by a lookup
     * must still be fully reset by a refresh, and must not stay protected afterwards.
     */
    public function testRefreshAfterALookupHasProtectedTheObject(): void
    {
        $post = $this->newPost('original');
        $id = $post->getId();
        $this->om->clear();

        $post = $this->om->getRepository(Post::class)->find($id);
        $post->setText('modified');

        // The lookup protects the change ...
        $this->om->createQueryBuilder(Post::class)->field('id')->equals($id)->getQuery()->getSingleResult();
        $this->assertSame('modified', $post->getText());

        // ... and the refresh must still drop it.
        $this->om->refresh($post);
        $this->assertSame('original', $post->getText(), 'refresh wins over the protection');

        // The object is clean again: a later lookup must refresh it, not freeze it.
        $this->rawUpdate('blog_post', $id, ['text' => 'changed elsewhere']);
        $this->om->createQueryBuilder(Post::class)->field('id')->equals($id)->getQuery()->getSingleResult();

        $this->assertSame(
            'changed elsewhere',
            $post->getText(),
            'after a refresh the object must not stay protected by a stale baseline'
        );
    }

    /**
     * A refresh replaces the baseline, so any baseline cached for the change detection
     * must go with it. Otherwise an object refreshed after the server changed would be
     * compared against the previous server state and look modified for ever.
     */
    public function testRefreshInvalidatesTheCachedBaseline(): void
    {
        $post = $this->newPost('v1');
        $id = $post->getId();
        $this->om->clear();

        $post = $this->om->getRepository(Post::class)->find($id);

        // A first lookup caches the baseline of an untouched object.
        $this->om->createQueryBuilder(Post::class)->field('id')->equals($id)->getQuery()->getSingleResult();

        // The row changes server side, then the object is refreshed from it.
        $this->rawUpdate('blog_post', $id, ['text' => 'v2']);
        $this->om->refresh($post);
        $this->assertSame('v2', $post->getText());

        // The row changes again: a lookup must still be able to refresh the object.
        $this->rawUpdate('blog_post', $id, ['text' => 'v3']);
        $this->om->createQueryBuilder(Post::class)->field('id')->equals($id)->getQuery()->getSingleResult();

        $this->assertSame('v3', $post->getText(), 'a stale cached baseline must not freeze the object');
    }

    // --- re-hydration must keep working ---------------------------------------

    public function testIncludeKeyStillPopulatesAnAlreadyManagedObject(): void
    {
        $user = new User();
        $user->setName('Alice');
        $user->setPassword('p');
        $this->om->persist($user);

        $post = new Post();
        $post->setText('hello');
        $post->setUser($user);
        $this->om->persist($post);
        $this->om->flush();

        $postId = $post->getId();
        $this->om->clear();

        // First load without include : user is a lazy proxy.
        $loaded = $this->om->getRepository(Post::class)->find($postId);
        $this->assertTrue($this->om->isUninitializedObject($loaded->getUser()));

        // Second load with include : the payload must initialize it.
        $this->om->createQueryBuilder(Post::class)
            ->field('id')->equals($postId)
            ->includeKey('user')
            ->getQuery()
            ->getSingleResult();

        $this->assertFalse(
            $this->om->isUninitializedObject($loaded->getUser()),
            'An unmodified managed object must still be completed by an includeKey payload'
        );
        $this->assertSame('Alice', $loaded->getUser()->getName());
    }

    /**
     * The protection is per field, not per object: a change made to one field survives
     * an includeKey query, and that same query still completes the associations the
     * object had not loaded yet.
     */
    public function testIncludeKeyCompletesAnObjectWhoseOtherFieldWasModified(): void
    {
        $user = new User();
        $user->setName('Alice');
        $user->setPassword('p');
        $this->om->persist($user);

        $post = new Post();
        $post->setText('original');
        $post->setUser($user);
        $this->om->persist($post);
        $this->om->flush();

        $postId = $post->getId();
        $this->om->clear();

        // Loaded without include: user is a lazy proxy, text comes from the server.
        $loaded = $this->om->getRepository(Post::class)->find($postId);
        $this->assertTrue($this->om->isUninitializedObject($loaded->getUser()));

        // A local change on one field ...
        $loaded->setText('modified');

        // ... then a query that includes the association.
        $this->om->createQueryBuilder(Post::class)
            ->field('id')->equals($postId)
            ->includeKey('user')
            ->getQuery()
            ->getSingleResult();

        $this->assertSame('modified', $loaded->getText(), 'the changed field keeps the local value');
        $this->assertFalse(
            $this->om->isUninitializedObject($loaded->getUser()),
            'the untouched association is still completed by the included payload'
        );
        $this->assertSame('Alice', $loaded->getUser()->getName());

        // And the change is the one that reaches Parse.
        $this->om->flush();
        $this->om->clear();
        $this->assertSame('modified', $this->om->getRepository(Post::class)->find($postId)->getText());
    }

    public function testUnmodifiedObjectIsStillRefreshedByALookup(): void
    {
        $post = $this->newPost('original');
        $id = $post->getId();
        $this->om->clear();

        $loaded = $this->om->getRepository(Post::class)->find($id);
        $this->rawUpdate('blog_post', $id, ['text' => 'changed elsewhere']);

        $this->om->createQueryBuilder(Post::class)->field('id')->equals($id)->getQuery()->getSingleResult();

        $this->assertSame(
            'changed elsewhere',
            $loaded->getText(),
            'Without a local change, a lookup keeps refreshing the object'
        );
    }

    public function testAFlushClearsTheProtectionSoLaterLookupsRefreshAgain(): void
    {
        $post = $this->newPost('original');
        $id = $post->getId();
        $this->om->clear();

        $post = $this->om->getRepository(Post::class)->find($id);
        $post->setText('modified');
        $this->om->flush();

        // The change is committed : the object is clean again.
        $this->rawUpdate('blog_post', $id, ['text' => 'changed elsewhere']);
        $this->om->createQueryBuilder(Post::class)->field('id')->equals($id)->getQuery()->getSingleResult();

        $this->assertSame(
            'changed elsewhere',
            $post->getText(),
            'Once flushed the object has no local change left and must be refreshed again'
        );
    }


    // --- every field type used in production ----------------------------------

    /**
     * @dataProvider provideFieldTypes
     */
    public function testChangeOnAnyFieldTypeSurvivesALookup(string $setter, string $getter, $value, callable $assert): void
    {
        $picture = new Picture();
        $picture->setFile('original');
        $this->om->persist($picture);
        $this->om->flush();
        $id = $picture->getId();
        $this->om->clear();

        $picture = $this->om->getRepository(Picture::class)->find($id);
        $picture->{$setter}($value);

        $this->om->createQueryBuilder(Picture::class)->field('id')->equals($id)->getQuery()->getSingleResult();

        $assert($picture->{$getter}(), 'kept in memory');

        $this->om->flush();
        $this->om->clear();

        $assert($this->om->getRepository(Picture::class)->find($id)->{$getter}(), 'persisted');
    }

    public static function provideFieldTypes(): iterable
    {
        yield 'string' => ['setFile', 'getFile', 'modified', function ($v, $stage) {
            self::assertSame('modified', $v, $stage);
        }];
        yield 'object' => ['setExif', 'getExif', ['a' => 1, 'b' => 'two'], function ($v, $stage) {
            self::assertSame(['a' => 1, 'b' => 'two'], $v, $stage);
        }];
        yield 'geopoint' => ['setLocation', 'getLocation', new ParseGeoPoint(48.85, 2.35), function ($v, $stage) {
            self::assertInstanceOf(ParseGeoPoint::class, $v, $stage);
            self::assertEqualsWithDelta(48.85, $v->getLatitude(), 0.0001, $stage);
        }];
    }

    public function testBooleanAndFloatChangesSurviveALookup(): void
    {
        $address = new Address();
        $address->setCity('home');
        $address->setIsDefault(false);
        $address->setOrder(1.5);
        $this->om->persist($address);
        $this->om->flush();
        $id = $this->om->getClassMetadata(Address::class)->getIdentifierValue($address);
        $this->om->clear();

        $address = $this->om->getRepository(Address::class)->find($id);
        $address->setIsDefault(true);
        $address->setOrder(9.25);

        $this->om->createQueryBuilder(Address::class)->field('id')->equals($id)->getQuery()->getSingleResult();

        $this->assertTrue($address->getIsDefault(), 'boolean kept');
        $this->assertEqualsWithDelta(9.25, $address->getOrder(), 0.0001, 'float kept');

        $this->om->flush();
        $this->om->clear();

        $reloaded = $this->om->getRepository(Address::class)->find($id);
        $this->assertTrue($reloaded->getIsDefault());
        $this->assertEqualsWithDelta(9.25, $reloaded->getOrder(), 0.0001);
    }

    public function testDateChangeSurvivesALookup(): void
    {
        $user = new User();
        $user->setName('dated');
        $user->setPassword('p');
        $user->setBirthday(new \DateTime('1980-01-01 10:00:00'));
        $this->om->persist($user);
        $this->om->flush();
        $id = $user->getId();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->find($id);
        $user->setBirthday(new \DateTime('1990-06-15 08:30:00'));

        $this->om->createQueryBuilder(User::class)->field('id')->equals($id)->getQuery()->getSingleResult();

        $this->assertSame('1990-06-15', $user->getBirthday()->format('Y-m-d'), 'date kept');

        $this->om->flush();
        $this->om->clear();

        $this->assertSame('1990-06-15', $this->om->getRepository(User::class)->find($id)->getBirthday()->format('Y-m-d'));
    }

    public function testEncryptedFieldChangeSurvivesALookup(): void
    {
        $doc = new SecureDocument();
        $doc->setSecretNote('original secret');
        $this->om->persist($doc);
        $this->om->flush();
        $id = $doc->getId();
        $this->om->clear();

        $doc = $this->om->getRepository(SecureDocument::class)->find($id);
        $doc->setSecretNote('new secret');

        $this->om->createQueryBuilder(SecureDocument::class)->field('id')->equals($id)->getQuery()->getSingleResult();

        $this->assertSame('new secret', $doc->getSecretNote(), 'encrypted value kept in memory');

        $this->om->flush();
        $this->om->clear();

        $this->assertSame('new secret', $this->om->getRepository(SecureDocument::class)->find($id)->getSecretNote());
    }

    // --- other relation shapes -------------------------------------------------

    /**
     * User::$addresses uses implementation "relation", i.e. a ParseRelation instead
     * of an array of pointers : another collection persister, another code path.
     */
    public function testRelationCollectionChangeSurvivesALookup(): void
    {
        $address = new Address();
        $address->setCity('home');
        $this->om->persist($address);

        $user = new User();
        $user->setName('with relation');
        $user->setPassword('p');
        $user->getAddresses()->add($address);
        $this->om->persist($user);
        $this->om->flush();

        $userId = $user->getId();
        $this->om->clear();

        $user = $this->om->getRepository(User::class)->find($userId);
        $this->assertCount(1, $user->getAddresses());
        $user->getAddresses()->removeElement($user->getAddresses()->first());

        $this->om->createQueryBuilder(User::class)->field('id')->equals($userId)->getQuery()->getSingleResult();

        $this->assertCount(0, $user->getAddresses(), 'The relation removal must survive the lookup');
    }

    /**
     * The inverse side is read only, so a lookup may keep refreshing it; what must
     * not happen is the owning-side change being lost.
     */
    public function testOwningSideChangeSurvivesALookupOfTheInverseSide(): void
    {
        [$articleId, $keptId] = $this->newArticleWithTags();
        $this->om->clear();

        $article = $this->om->getRepository(Article::class)->find($articleId);
        foreach ($article->getTags()->toArray() as $tag) {
            if ($tag->getId() !== $keptId) {
                $article->getTags()->removeElement($tag);
            }
        }

        // Querying the Tag side brings the Article back through the inverse relation.
        $this->om->getRepository(Tag::class)->findAll();

        $this->assertCount(1, $article->getTags(), 'The owning-side removal must survive');

        $this->om->flush();
        $this->om->clear();

        $this->assertCount(1, $this->om->getRepository(Article::class)->find($articleId)->getTags());
    }

    public function testChangeSurvivesALookupBuriedInAnIncludeGraph(): void
    {
        [$rootId, $aId, $bId] = $this->newChain();
        $this->om->clear();

        // B is loaded and modified on its own...
        $b = $this->om->getRepository(ChainNode::class)->find($bId);
        $b->setLabel('modified B');

        // ...then it comes back as a nested include of the root graph.
        $this->om->createQueryBuilder(ChainNode::class)
            ->field('id')->equals($rootId)
            ->includeKey('first')
            ->includeKey('second')
            ->getQuery()
            ->getSingleResult();

        $this->assertSame('modified B', $b->getLabel(), 'A nested include must not revert B');

        $this->om->flush();
        $this->om->clear();

        $this->assertSame('modified B', $this->om->getRepository(ChainNode::class)->find($bId)->getLabel());
    }

    public function testAclChangeSurvivesALookup(): void
    {
        $picture = new Picture();
        $picture->setFile('acl');
        $this->om->persist($picture);
        $this->om->flush();
        $id = $picture->getId();
        $this->om->clear();

        $picture = $this->om->getRepository(Picture::class)->find($id);
        $picture->setPublicAcl(true, false);

        $this->om->createQueryBuilder(Picture::class)->field('id')->equals($id)->getQuery()->getSingleResult();

        $this->assertTrue($picture->getPublicAclReadAccess(), 'read access kept');
        $this->assertFalse($picture->getPublicAclWriteAccess(), 'the revoked write access must not come back');
    }

    /**
     * A lookup fired from inside a commit is the exact production shape: a listener
     * changes an object in preFlush, then queries the same class again.
     */
    public function testChangeSurvivesALookupFiredDuringTheCommit(): void
    {
        [$rootId] = $this->newChain();
        $this->om->clear();

        $root = $this->om->getRepository(ChainNode::class)->find($rootId);

        $om = $this->om;
        $listener = new class($om, $rootId) {
            public function __construct(private $om, private string $rootId) {}
            public function preFlush(): void
            {
                $this->om->createQueryBuilder(ChainNode::class)
                    ->field('id')->equals($this->rootId)
                    ->getQuery()
                    ->getSingleResult();
            }
        };
        $this->om->getEventManager()->addEventListener([\Redking\ParseBundle\Events::preFlush], $listener);

        $root->setFirst(null);
        $this->om->flush();
        $this->om->clear();

        $this->assertNull(
            $this->om->getRepository(ChainNode::class)->find($rootId)->getFirst(),
            'A lookup inside the commit must not restore the pointer'
        );
    }

    /**
     * do_not_manage returns early on an identity map hit, so it hands back the managed
     * instance untouched. That contract is unchanged: what matters is that the local
     * change is neither reverted nor leaked into a detached copy of another object.
     */
    public function testDoNotManageHintIsUnaffected(): void
    {
        $post = $this->newPost('original');
        $other = $this->newPost('other');
        $id = $post->getId();
        $otherId = $other->getId();
        $this->om->clear();

        $post = $this->om->getRepository(Post::class)->find($id);
        $post->setText('modified');

        $results = $this->om->createQueryBuilder(Post::class)
            ->getQuery()
            ->setHints(['doctrine.do_not_manage' => true])
            ->execute();

        $byId = [];
        foreach ($results as $result) {
            $byId[$this->om->getClassMetadata(Post::class)->getIdentifierValue($result)] = $result;
        }

        $this->assertSame('modified', $post->getText(), 'the managed object keeps its change');
        $this->assertSame($post, $byId[$id], 'an already managed object is handed back as is');
        $this->assertSame('other', $byId[$otherId]->getText(), 'the other object is a detached read-only copy');
        $this->assertFalse($this->om->contains($byId[$otherId]), 'and it is not managed');
    }

    // --- helpers --------------------------------------------------------------


    private function newPost(string $text): Post
    {
        $post = new Post();
        $post->setText($text);
        $this->om->persist($post);
        $this->om->flush();

        return $post;
    }

    /**
     * root -> first = A, second = B ; A -> first = B.
     *
     * @return array{0: string, 1: string, 2: string} root, A and B ids
     */
    private function newChain(): array
    {
        $b = new ChainNode();
        $b->setLabel('B');
        $this->om->persist($b);

        $a = new ChainNode();
        $a->setLabel('A');
        $a->setFirst($b);
        $this->om->persist($a);

        $root = new ChainNode();
        $root->setLabel('root');
        $root->setFirst($a);
        $root->setSecond($b);
        $this->om->persist($root);

        $this->om->flush();

        return [$root->getId(), $a->getId(), $b->getId()];
    }

    /**
     * @return array{0: string, 1: string} article id and the id of the tag to keep
     */
    private function newArticleWithTags(): array
    {
        $kept = new Tag();
        $kept->setName('kept');
        $this->om->persist($kept);

        $dropped = new Tag();
        $dropped->setName('dropped');
        $this->om->persist($dropped);

        $article = new Article();
        $article->setTitle('an article');
        $article->getTags()->add($kept);
        $article->getTags()->add($dropped);
        $this->om->persist($article);

        $this->om->flush();

        return [$article->getId(), $kept->getId()];
    }

    /**
     * Change a row behind the object manager's back, to tell a refresh from a no-op.
     */
    private function rawUpdate(string $collection, string $id, array $values): void
    {
        $query = new \Parse\ParseQuery($collection);
        $object = $query->get($id, true);
        foreach ($values as $field => $value) {
            $object->set($field, $value);
        }
        $object->save(true);
    }
}
