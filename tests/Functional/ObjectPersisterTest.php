<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseObject;
use Parse\ParseQuery;
use Parse\ParseRole;
use Redking\ParseBundle\Persisters\ObjectPersister;
use Redking\ParseBundle\Tests\Models\Blog\ChainNode;
use Redking\ParseBundle\Tests\Models\Blog\Role;

/**
 * Coverage for ObjectPersister helpers not exercised by the higher-level flush
 * tests: ParseObject instantiation, query profiling/logging, single update,
 * existence checks and refresh.
 */
class ObjectPersisterTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        ChainNode::class,
    ];

    private function persister(string $class): ObjectPersister
    {
        return $this->om->getUnitOfWork()->getObjectPersister($class);
    }

    public function testInstanciateParseObjectUsesCollectionClass(): void
    {
        $parseObject = $this->persister(ChainNode::class)->instanciateParseObject();

        $this->assertInstanceOf(ParseObject::class, $parseObject);
        $this->assertNotInstanceOf(ParseRole::class, $parseObject);
        $this->assertSame('blog_chain_node', $parseObject->getClassName());
    }

    public function testInstanciateParseObjectReturnsRoleForRoleCollection(): void
    {
        $parseObject = $this->persister(Role::class)->instanciateParseObject();

        $this->assertInstanceOf(ParseRole::class, $parseObject);
    }

    public function testLogQueryWithArrayPayload(): void
    {
        $captured = null;
        $this->om->getConfiguration()->setLoggerCallable(function (array $q) use (&$captured) {
            $captured = $q;
        });

        $this->persister(ChainNode::class)->logQuery(['type' => 'custom', 'id' => 'abc']);

        $this->assertSame('blog_chain_node', $captured['className']);
        $this->assertSame('custom', $captured['type']);
        $this->assertSame('abc', $captured['id']);
    }

    public function testLogQueryWithParseQueryMergesOptions(): void
    {
        $captured = null;
        $this->om->getConfiguration()->setLoggerCallable(function (array $q) use (&$captured) {
            $captured = $q;
        });

        $parseQuery = new ParseQuery('blog_chain_node');
        $parseQuery->equalTo('label', 'x');

        $this->persister(ChainNode::class)->logQuery($parseQuery);

        $this->assertSame('blog_chain_node', $captured['className']);
        $this->assertArrayHasKey('where', $captured);
    }

    public function testLogQueryWithoutLoggerIsNoop(): void
    {
        // No logger configured by default: must not error.
        $this->persister(ChainNode::class)->logQuery(['type' => 'x']);
        $this->addToAssertionCount(1);
    }

    public function testProfileQueryInvokesProfiler(): void
    {
        $calls = 0;
        $this->om->getConfiguration()->setProfilerCallable(function () use (&$calls) {
            ++$calls;
        });

        $this->persister(ChainNode::class)->profileQuery();

        $this->assertSame(1, $calls);
    }

    public function testUpdatePersistsAParseObject(): void
    {
        $persister = $this->persister(ChainNode::class);
        $parseObject = $persister->instanciateParseObject();
        $parseObject->set('label', 'persisted-directly');

        $persister->update($parseObject, ['label' => [null, 'persisted-directly']]);

        $this->assertNotNull($parseObject->getObjectId(), 'update() must save the ParseObject');
    }

    public function testExistsReflectsDatabaseState(): void
    {
        $node = new ChainNode();
        $node->setLabel('present');
        $this->om->persist($node);
        $this->om->flush();

        $this->assertTrue($this->persister(ChainNode::class)->exists($node));

        // A managed-looking object whose id is unknown to the database does not exist.
        $ghost = new ChainNode();
        $this->om->getClassMetadata(ChainNode::class)->setIdentifierValue($ghost, 'doesNotExist01');
        $this->assertFalse($this->persister(ChainNode::class)->exists($ghost));
    }

    public function testRefreshReloadsFromDatabase(): void
    {
        $node = new ChainNode();
        $node->setLabel('original');
        $this->om->persist($node);
        $this->om->flush();

        // Mutate in memory only, then refresh: the DB value must win.
        $node->setLabel('dirty-in-memory');
        $this->om->refresh($node);

        $this->assertSame('original', $node->getLabel());
    }
}
