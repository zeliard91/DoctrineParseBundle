<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseGeoPoint;
use Redking\ParseBundle\Query\Expr;
use Redking\ParseBundle\Query\QueryArg;

/**
 * Coverage for the query Expr builder: every operator must record the right
 * Parse operator name and value under the current field (or at the top level
 * when no field is set). Expr needs a UnitOfWork (for references()), so this
 * uses the test ObjectManager — but none of these assertions hit Parse Server.
 */
class ExprTest extends \Redking\ParseBundle\Tests\TestCase
{
    protected static $modelSets = [];

    private function expr(): Expr
    {
        return new Expr($this->om->getUnitOfWork());
    }

    private function assertCriterion(Expr $expr, string $field, string $operator, $expectedValue): QueryArg
    {
        $query = $expr->getQuery();
        $this->assertArrayHasKey($field, $query);
        $this->assertArrayHasKey($operator, $query[$field]);
        $arg = $query[$field][$operator];
        $this->assertInstanceOf(QueryArg::class, $arg);
        $this->assertSame($expectedValue, $arg->getValue());

        return $arg;
    }

    public function testEqualsAndNotEqual(): void
    {
        $this->assertCriterion($this->expr()->field('name')->equals('foo'), 'name', 'equalTo', 'foo');
        $this->assertCriterion($this->expr()->field('name')->notEqual('bar'), 'name', 'notEqualTo', 'bar');
    }

    public function testInUsesContainedInAndNotInUsesNotContainedIn(): void
    {
        $this->assertCriterion($this->expr()->field('tags')->in(['a', 'b']), 'tags', 'containedIn', ['a', 'b']);
        $this->assertCriterion($this->expr()->field('tags')->notIn(['c']), 'tags', 'notContainedIn', ['c']);
    }

    public function testInReindexesAssociativeValues(): void
    {
        $this->assertCriterion($this->expr()->field('tags')->in([3 => 'a', 7 => 'b']), 'tags', 'containedIn', ['a', 'b']);
    }

    public function testExistsUsesDistinctOperatorsForTrueAndFalse(): void
    {
        $this->assertCriterion($this->expr()->field('email')->exists(true), 'email', 'exists', true);
        $this->assertCriterion($this->expr()->field('email')->exists(false), 'email', 'doesNotExist', false);
    }

    public function testComparisonOperators(): void
    {
        $this->assertCriterion($this->expr()->field('age')->gt(18), 'age', 'greaterThan', 18);
        $this->assertCriterion($this->expr()->field('age')->gte(18), 'age', 'greaterThanOrEqualTo', 18);
        $this->assertCriterion($this->expr()->field('age')->lt(65), 'age', 'lessThan', 65);
        $this->assertCriterion($this->expr()->field('age')->lte(65), 'age', 'lessThanOrEqualTo', 65);
    }

    public function testContainsAndRegexWithModifiers(): void
    {
        $this->assertCriterion($this->expr()->field('bio')->contains('word'), 'bio', 'contains', 'word');

        $arg = $this->assertCriterion($this->expr()->field('bio')->regex('^a', 'i'), 'bio', 'matches', '^a');
        $this->assertSame('i', $arg->getArgument(), 'regex modifiers must be stored as the QueryArg argument');
    }

    public function testGeoOperators(): void
    {
        $point = new ParseGeoPoint(48.85, 2.35);

        $this->assertCriterion($this->expr()->field('loc')->near($point), 'loc', 'near', $point);

        $arg = $this->assertCriterion($this->expr()->field('loc')->withinMiles($point, 10), 'loc', 'withinMiles', $point);
        $this->assertSame(10, $arg->getArgument());

        $arg = $this->assertCriterion($this->expr()->field('loc')->withinKilometers($point, 5), 'loc', 'withinKilometers', $point);
        $this->assertSame(5, $arg->getArgument());
    }

    public function testWithinGeoBox(): void
    {
        $sw = new ParseGeoPoint(40, -74);
        $ne = new ParseGeoPoint(41, -73);

        $arg = $this->assertCriterion($this->expr()->field('loc')->withinGeoBox($sw, $ne), 'loc', 'withinGeoBox', $sw);
        $this->assertSame($ne, $arg->getArgument());
    }

    public function testOperatorWithoutCurrentFieldIsRecordedAtTopLevel(): void
    {
        $query = $this->expr()->operator('customOp', 'val')->getQuery();

        $this->assertArrayHasKey('customOp', $query);
        $this->assertInstanceOf(QueryArg::class, $query['customOp']);
        $this->assertSame('val', $query['customOp']->getValue());
    }

    public function testAddOrAcceptsExprAndRawArray(): void
    {
        $sub = $this->expr()->field('a')->equals(1);

        $query = $this->expr()
            ->addOr($sub)
            ->addOr(['raw' => 'criteria'])
            ->getQuery();

        $this->assertArrayHasKey('$or', $query);
        $this->assertCount(2, $query['$or']);
        // First branch is the nested Expr's criteria array.
        $this->assertArrayHasKey('a', $query['$or'][0]);
        // Second branch is the raw array, passed through untouched.
        $this->assertSame(['raw' => 'criteria'], $query['$or'][1]);
    }

    public function testAggregateStoresPipelineUnderDedicatedKey(): void
    {
        $query = $this->expr()->aggregate(['group' => ['x' => 1]])->getQuery();

        $this->assertSame(['group' => ['x' => 1]], $query['$aggregate']);
    }
}
