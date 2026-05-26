<?php

namespace Redking\ParseBundle\Tests\Functional;

use Redking\ParseBundle\Query;
use Redking\ParseBundle\Tests\Models\Blog\ChainNode;

/**
 * Coverage for QueryBuilder -> Query translation paths that the execution-level
 * QueryTest does not exercise: setCriteria(), the "field.id" pointer notation,
 * nested includeKey(), unmapped select fallback, plus Query lifecycle accessors
 * and guards. Translation is inspected via ParseQuery::_getOptions() (no server
 * round-trip); a couple of cases execute against the test Parse Server.
 */
class QueryBuilderTranslationTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [
        ChainNode::class,
    ];

    private function options(\Redking\ParseBundle\QueryBuilder $qb): array
    {
        return $qb->getQuery()->getParseQuery()->_getOptions();
    }

    // --- Query lifecycle / guards -------------------------------------------

    public function testConstructorRejectsInvalidType(): void
    {
        $class = $this->om->getClassMetadata(ChainNode::class);

        $this->expectException(\InvalidArgumentException::class);
        new Query($this->om, $class, ['type' => 999]);
    }

    public function testTypeHydrateAndManagerAccessors(): void
    {
        $query = $this->om->createQueryBuilder(ChainNode::class)->getQuery();

        $this->assertSame(Query::TYPE_FIND, $query->getType());
        $this->assertSame($query, $query->setType(Query::TYPE_COUNT));
        $this->assertSame(Query::TYPE_COUNT, $query->getType());

        $query->setHydrate(false);
        $this->assertSame($query, $query->setHints(['x' => 1]));
        $this->assertSame($this->om, $query->getObjectManager());
    }

    public function testExecuteWithUnsupportedTypeThrows(): void
    {
        $query = $this->om->createQueryBuilder(ChainNode::class)->getQuery();
        $query->setType(Query::TYPE_UPDATE);

        $this->expectException(\Exception::class);
        $query->execute();
    }

    // --- setCriteria ---------------------------------------------------------

    public function testSetCriteriaScalarUsesEquality(): void
    {
        $qb = $this->om->createQueryBuilder(ChainNode::class)->setCriteria(['label' => 'foo']);

        $opts = $this->options($qb);
        $this->assertSame(['$eq' => 'foo'], $opts['where']['label']);
    }

    public function testSetCriteriaNullUsesContainedInNull(): void
    {
        $qb = $this->om->createQueryBuilder(ChainNode::class)->setCriteria(['label' => null]);

        $opts = $this->options($qb);
        $this->assertSame(['$in' => [null]], $opts['where']['label']);
    }

    public function testSetCriteriaWithManagedObjectUsesPointerReference(): void
    {
        $target = new ChainNode();
        $target->setLabel('target');
        $this->om->persist($target);
        $this->om->flush();

        $qb = $this->om->createQueryBuilder(ChainNode::class)->setCriteria(['first' => $target]);

        // references() resolves to the original ParseObject; equalTo encodes it
        // as a Pointer under the '$eq' operator.
        $pointer = $this->options($qb)['where']['first']['$eq'];
        $this->assertSame('Pointer', $pointer['__type']);
        $this->assertSame('blog_chain_node', $pointer['className']);
        $this->assertSame($target->getId(), $pointer['objectId']);
    }

    // --- applyQuery translation branches ------------------------------------

    public function testDotIdNotationBecomesPointer(): void
    {
        $qb = $this->om->createQueryBuilder(ChainNode::class);
        $qb->field('first.id')->equals('ABCD123456');

        // The "field.id" notation builds a Pointer stdClass kept as-is by the encoder.
        $pointer = $this->options($qb)['where']['first']['$eq'];
        $this->assertSame('Pointer', $pointer->__type);
        $this->assertSame('blog_chain_node', $pointer->className);
        $this->assertSame('ABCD123456', $pointer->objectId);
    }

    public function testNestedIncludeKeyIsTranslated(): void
    {
        $qb = $this->om->createQueryBuilder(ChainNode::class)->includeKey('first.first');

        $this->assertSame('first.first', $this->options($qb)['include']);
    }

    public function testSelectFallsBackToRawNameForUnmappedField(): void
    {
        $qb = $this->om->createQueryBuilder(ChainNode::class)->select('label', 'rawColumn');

        $keys = explode(',', $this->options($qb)['keys']);
        $this->assertContains('label', $keys);
        $this->assertContains('rawColumn', $keys);
    }

    public function testSkipIsTranslated(): void
    {
        $qb = $this->om->createQueryBuilder(ChainNode::class)->skip(7);

        $this->assertSame(7, $this->options($qb)['skip']);
        $this->assertNull($qb->getLimit(), 'getLimit() is null when no limit is set');
    }

    public function testFindAppliesDefaultHighLimitWhenUnset(): void
    {
        $qb = $this->om->createQueryBuilder(ChainNode::class)->field('label')->equals('x');

        $this->assertSame(999999999999, $this->options($qb)['limit']);
    }

    public function testExplicitLimitOverridesDefault(): void
    {
        $qb = $this->om->createQueryBuilder(ChainNode::class)->limit(3);

        $this->assertSame(3, $this->options($qb)['limit']);
        $this->assertSame(3, $qb->getLimit());
    }

    // --- execution-level lifecycle ------------------------------------------

    public function testIterateReturnsTraversableOverResults(): void
    {
        $node = new ChainNode();
        $node->setLabel('iter');
        $this->om->persist($node);
        $this->om->flush();
        $this->om->clear();

        $iterator = $this->om->createQueryBuilder(ChainNode::class)
            ->field('label')->equals('iter')
            ->getQuery()
            ->iterate();

        $labels = [];
        foreach ($iterator as $result) {
            $labels[] = $result->getLabel();
        }
        $this->assertSame(['iter'], $labels);
    }
}
