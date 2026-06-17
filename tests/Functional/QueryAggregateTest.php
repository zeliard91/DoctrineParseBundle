<?php

namespace Redking\ParseBundle\Tests\Functional;

use Redking\ParseBundle\Tests\Models\Blog\BankOperation;
use Redking\ParseBundle\Tests\Models\Blog\Post;
use Redking\ParseBundle\Tests\Models\Blog\User;

/**
 * Functional coverage for aggregation pipelines executed against a real Parse
 * Server. These golden-value tests must pass identically on Parse Server 4.5.2
 * (legacy pipeline syntax) and >= 6.x (native MongoDB syntax), proving that
 * QueryBuilder::getAggregateForNewParseVersion() preserves results across the
 * migration. Each test mirrors a pipeline pattern used in production services.
 */
class QueryAggregateTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        'Redking\ParseBundle\Tests\Models\Blog\User',
        'Redking\ParseBundle\Tests\Models\Blog\Post',
        'Redking\ParseBundle\Tests\Models\Blog\BankOperation',
    ];

    private function createUser(string $name): User
    {
        $user = new User();
        $user->setPassword('p4ss');
        $user->setName($name);
        $this->om->persist($user);

        return $user;
    }

    private function createPost(string $text, User $user): Post
    {
        $post = new Post();
        $post->setText($text);
        $post->setUser($user);
        $this->om->persist($post);

        return $post;
    }

    private function createBankOperation(float $amount): BankOperation
    {
        $op = new BankOperation();
        $op->setAmount($amount);
        $this->om->persist($op);

        return $op;
    }

    /**
     * Index aggregation results by their group id, returned under the "objectId"
     * key by Parse Server on both versions. Accepts array or object rows.
     */
    private function indexByObjectId(array $results): array
    {
        $indexed = [];
        foreach ($results as $row) {
            $row = (array) $row;
            $indexed[$row['objectId']] = $row;
        }

        return $indexed;
    }

    /**
     * Pattern: $substr extraction of a pointer id ($_p_user) + $group by the
     * extracted id + conditional $cond/$sum counting. Mirrors the reporting
     * pipelines that group by establishment/adherent ids.
     */
    public function testSubstrPointerExtractionWithConditionalCount(): void
    {
        $userA = $this->createUser('UserA');
        $userB = $this->createUser('UserB');
        $this->om->flush();

        $this->createPost('ham', $userA);
        $this->createPost('ham', $userA);
        $this->createPost('spam', $userA);
        $this->createPost('ham', $userB);
        $this->createPost('spam', $userB);
        $this->om->flush();

        $pipeline = [
            'project' => [
                'objectId' => 1,
                'text' => 1,
                'userId' => ['$substr' => ['$_p_user', strlen('_User$'), -1]],
            ],
            'group' => [
                'objectId' => '$userId',
                'nbPosts' => ['$sum' => 1],
                'nbHam' => ['$sum' => ['$cond' => [['$eq' => ['$text', 'ham']], 1, 0]]],
            ],
        ];

        $results = $this->om->getRepository(Post::class)
            ->createQueryBuilder()
            ->aggregate($pipeline)
            ->getQuery()
            ->execute();

        $byUser = $this->indexByObjectId($results);

        $this->assertCount(2, $byUser);
        $this->assertArrayHasKey($userA->getId(), $byUser);
        $this->assertArrayHasKey($userB->getId(), $byUser);
        $this->assertSame(3, (int) $byUser[$userA->getId()]['nbPosts']);
        $this->assertSame(2, (int) $byUser[$userA->getId()]['nbHam']);
        $this->assertSame(2, (int) $byUser[$userB->getId()]['nbPosts']);
        $this->assertSame(1, (int) $byUser[$userB->getId()]['nbHam']);
    }

    /**
     * Production pipelines reference a pointer either by its Parse field name
     * ("user") or by its raw Mongo name ("_p_user"), often mixed in the same
     * pipeline. Parse Server must translate the Parse field name to the Mongo
     * name inside $match on BOTH versions, otherwise such matches would silently
     * return nothing on Parse 6. This locks that behaviour down.
     */
    public function testMatchOnPointerByParseFieldName(): void
    {
        $userA = $this->createUser('UserA');
        $this->om->flush();

        $this->createPost('a', $userA);
        $this->createPost('b', $userA);
        $this->createPost('c', $userA);
        $this->om->flush();

        $pipeline = [
            'match' => ['user' => ['$ne' => null]],
            'group' => ['objectId' => null, 'nb' => ['$sum' => 1]],
        ];

        $results = $this->om->getRepository(Post::class)
            ->createQueryBuilder()
            ->aggregate($pipeline)
            ->getQuery()
            ->execute();

        $this->assertCount(1, $results);
        $this->assertSame(3, (int) ((array) $results[0])['nb']);
    }

    /**
     * Same as above but referencing the raw Mongo pointer field "_p_user".
     */
    public function testMatchOnPointerByRawMongoFieldName(): void
    {
        $userA = $this->createUser('UserA');
        $this->om->flush();

        $this->createPost('a', $userA);
        $this->createPost('b', $userA);
        $this->createPost('c', $userA);
        $this->om->flush();

        $pipeline = [
            'match' => ['_p_user' => ['$ne' => null]],
            'group' => ['objectId' => null, 'nb' => ['$sum' => 1]],
        ];

        $results = $this->om->getRepository(Post::class)
            ->createQueryBuilder()
            ->aggregate($pipeline)
            ->getQuery()
            ->execute();

        $this->assertCount(1, $results);
        $this->assertSame(3, (int) ((array) $results[0])['nb']);
    }

    /**
     * Pattern: $match with $in + single $group (objectId => null) summing
     * conditional counters. Mirrors global reporting totals.
     */
    public function testMatchInWithGlobalConditionalSums(): void
    {
        $userA = $this->createUser('UserA');
        $this->om->flush();

        $this->createPost('ham', $userA);
        $this->createPost('ham', $userA);
        $this->createPost('spam', $userA);
        $this->createPost('other', $userA);
        $this->om->flush();

        $pipeline = [
            'match' => ['text' => ['$in' => ['ham', 'spam']]],
            'group' => [
                'objectId' => null,
                'total' => ['$sum' => 1],
                'nbSpam' => ['$sum' => ['$cond' => [['$eq' => ['$text', 'spam']], 1, 0]]],
            ],
        ];

        $results = $this->om->getRepository(Post::class)
            ->createQueryBuilder()
            ->aggregate($pipeline)
            ->getQuery()
            ->execute();

        $this->assertCount(1, $results);
        $row = (array) $results[0];
        $this->assertSame(3, (int) $row['total']);
        $this->assertSame(1, (int) $row['nbSpam']);
    }

    /**
     * Pattern: $group summing a numeric field. Mirrors invoice/amount totals.
     */
    public function testGroupSumOfNumericField(): void
    {
        $this->createBankOperation(10.0);
        $this->createBankOperation(20.0);
        $this->createBankOperation(30.0);
        $this->createBankOperation(40.0);
        $this->om->flush();

        $pipeline = [
            'group' => [
                'objectId' => null,
                'totalAmount' => ['$sum' => '$amount'],
                'nb' => ['$sum' => 1],
            ],
        ];

        $results = $this->om->getRepository(BankOperation::class)
            ->createQueryBuilder()
            ->aggregate($pipeline)
            ->getQuery()
            ->execute();

        $this->assertCount(1, $results);
        $row = (array) $results[0];
        $this->assertSame(100.0, (float) $row['totalAmount']);
        $this->assertSame(4, (int) $row['nb']);
    }

    /**
     * Pattern: $sort + $skip + $limit pagination. Mirrors the paginated export
     * pipelines (skip/limit stages were previously not converted for Parse 6).
     */
    public function testSortSkipLimitPagination(): void
    {
        $this->createBankOperation(10.0);
        $this->createBankOperation(20.0);
        $this->createBankOperation(30.0);
        $this->createBankOperation(40.0);
        $this->om->flush();

        $pipeline = [
            'sort' => ['amount' => 1],
            'skip' => 1,
            'limit' => 2,
        ];

        $results = $this->om->getRepository(BankOperation::class)
            ->createQueryBuilder()
            ->aggregate($pipeline)
            ->getQuery()
            ->execute();

        $this->assertCount(2, $results);
        $amounts = array_map(fn ($row) => (float) ((array) $row)['amount'], $results);
        $this->assertSame([20.0, 30.0], $amounts);
    }
}
