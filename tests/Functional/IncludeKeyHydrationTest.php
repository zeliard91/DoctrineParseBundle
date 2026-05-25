<?php

namespace Redking\ParseBundle\Tests\Functional;

use Redking\ParseBundle\Tests\Models\Blog\ChainNode;
use Redking\ParseBundle\Tests\Models\Blog\Post;
use Redking\ParseBundle\Tests\Models\Blog\User;

/**
 * Regression tests for the multi-includeKey bug: when a Parse query carries
 * several top-level include clauses, the Hydrator processes nested Pointer
 * fields before the matching top-level included payload, which leaves a lazy
 * proxy in the identity map. The proxy is then re-hydrated by
 * UnitOfWork::getOrCreateObject(), but Doctrine\Persistence\Reflection
 * \RuntimeReflectionProperty::setValue() flips the VarExporter LazyObjectState
 * back to UNINITIALIZED_FULL after each write — so the proxy ends up populated
 * yet still flagged as lazy, and any later property access triggers a
 * redundant Parse query.
 */
class IncludeKeyHydrationTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        User::class,
        Post::class,
        ChainNode::class,
    ];

    /**
     * Scenario A — re-hydration of a pre-existing proxy through includeKey().
     *
     * Place a User proxy in the identity map up-front (this mirrors what
     * happens when a nested Pointer in a sibling included object is processed
     * first), then run a Post query with includeKey('user'). The included
     * payload must mark the existing proxy as initialized; touching it must
     * not emit any extra query.
     */
    public function testIncludeKeyRehydratesPreExistingProxy(): void
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
        $userId = $user->getId();
        $this->om->clear();

        $userProxy = $this->om->getReference(User::class, $userId);
        $this->assertTrue(
            $this->om->isUninitializedObject($userProxy),
            'getReference must return an uninitialized proxy'
        );

        $queries = [];
        $this->om->getConfiguration()->setLoggerCallable(
            static function (array $q) use (&$queries) { $queries[] = $q; }
        );

        $loadedPost = $this->om->createQueryBuilder(Post::class)
            ->field('id')->equals($postId)
            ->includeKey('user')
            ->getQuery()
            ->getSingleResult();

        $this->assertNotNull($loadedPost);
        $this->assertCount(1, $queries, 'Only the Post query should fire');

        $this->assertFalse(
            $this->om->isUninitializedObject($userProxy),
            'The included payload must mark the existing proxy as initialized'
        );

        $this->assertSame('Alice', $loadedPost->getUser()->getName());
        $this->assertCount(
            1,
            $queries,
            'Reading after include-driven re-hydration must not query'
        );
    }

    /**
     * A direct query that returns a payload for an entity already represented
     * by a proxy in the identity map must hydrate the proxy in place. The
     * proxy must be flipped to INITIALIZED after the query so that later
     * property accesses do not trigger the lazy initializer.
     */
    public function testQueryRehydratesPreExistingProxy(): void
    {
        $user = new User();
        $user->setName('Bob');
        $user->setPassword('p');
        $this->om->persist($user);
        $this->om->flush();

        $userId = $user->getId();
        $this->om->clear();

        $userProxy = $this->om->getReference(User::class, $userId);
        $this->assertTrue($this->om->isUninitializedObject($userProxy));

        $queries = [];
        $this->om->getConfiguration()->setLoggerCallable(
            static function (array $q) use (&$queries) { $queries[] = $q; }
        );

        $loaded = $this->om->createQueryBuilder(User::class)
            ->field('id')->equals($userId)
            ->getQuery()
            ->getSingleResult();

        $this->assertSame($userProxy, $loaded, 'The proxy must be returned in place');
        $this->assertCount(1, $queries, 'Only the User query should fire');
        $this->assertFalse(
            $this->om->isUninitializedObject($userProxy),
            'The query payload must mark the existing proxy as initialized'
        );

        $this->assertSame('Bob', $loaded->getName());
        $this->assertCount(1, $queries, 'Reading after re-hydration must not query');
    }

    /**
     * Reproduces the user-reported "mixed proxy / non-proxy" symptom across
     * multiple top-level includes. The graph is:
     *
     *     root --first--> A --first--> B
     *     root --second--> B
     *
     * When root is queried with includeKey('first').includeKey('second'),
     * Parse Server fully embeds A inside root.first and B inside root.second.
     * During hydration, A's nested A.first Pointer to B is encountered before
     * the top-level root.second payload — without the fix, that nested
     * Pointer creates a generated proxy for B which then survives as the
     * identity-map entry, so root.second ends up as the proxy class while
     * root.first stays the original class.
     *
     * After the fix, both included relations must be instances of the
     * original entity class, and the nested A.first must reference the very
     * same B as root.second (identity preserved).
     */
    public function testMultipleIncludesAllHydrateToOriginalClass(): void
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
        $rootId = $root->getId();
        $this->om->clear();

        $queries = [];
        $this->om->getConfiguration()->setLoggerCallable(
            static function (array $q) use (&$queries) { $queries[] = $q; }
        );

        $loadedRoot = $this->om->createQueryBuilder(ChainNode::class)
            ->field('id')->equals($rootId)
            ->includeKey('first')
            ->includeKey('second')
            ->getQuery()
            ->getSingleResult();

        $this->assertNotNull($loadedRoot);
        $this->assertCount(1, $queries, 'Multi-include must collapse into one Parse query');

        $first  = $loadedRoot->getFirst();
        $second = $loadedRoot->getSecond();

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame('A', $first->getLabel());
        $this->assertSame('B', $second->getLabel());

        $this->assertSame(
            ChainNode::class,
            get_class($first),
            'root.first must be the entity class'
        );
        $this->assertSame(
            ChainNode::class,
            get_class($second),
            'root.second must be the entity class even though its id was first seen as a nested Pointer inside root.first'
        );

        $this->assertSame(
            $second,
            $first->getFirst(),
            'A.first must be the same instance as root.second (identity preserved)'
        );

        $this->assertCount(1, $queries, 'No extra query may fire while reading the included graph');
    }
}
