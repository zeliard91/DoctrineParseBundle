<?php

namespace Redking\ParseBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\QueryBuilder;

/**
 * Unit coverage for QueryBuilder::getAggregateForNewParseVersion(), the pure
 * static transform applied to aggregation pipelines on Parse Server >= 6:
 * legacy stage names are prefixed with '$' and 'objectId' becomes '_id'.
 */
class QueryBuilderAggregateTest extends TestCase
{
    public function testStageNamesArePrefixedWithDollar(): void
    {
        $out = QueryBuilder::getAggregateForNewParseVersion([
            'match' => ['status' => 'active'],
            'sort' => ['createdAt' => -1],
        ]);

        $this->assertArrayHasKey('$match', $out);
        $this->assertArrayHasKey('$sort', $out);
        $this->assertArrayNotHasKey('match', $out);
    }

    public function testObjectIdKeyBecomesUnderscoreId(): void
    {
        $out = QueryBuilder::getAggregateForNewParseVersion([
            'group' => ['objectId' => '$category', 'total' => ['$sum' => 1]],
        ]);

        $this->assertSame(
            ['$group' => ['_id' => '$category', 'total' => ['$sum' => 1]]],
            $out
        );
    }

    public function testTransformRecursesIntoNestedArrays(): void
    {
        $out = QueryBuilder::getAggregateForNewParseVersion([
            'lookup' => [
                'from' => 'Other',
                'pipeline' => ['match' => ['objectId' => 'x']],
            ],
        ]);

        $this->assertSame(
            ['$lookup' => [
                'from' => 'Other',
                'pipeline' => ['$match' => ['_id' => 'x']],
            ]],
            $out
        );
    }

    public function testUnknownKeysAndScalarValuesArePreserved(): void
    {
        $out = QueryBuilder::getAggregateForNewParseVersion([
            'project' => ['name' => 1, 'customField' => '$foo'],
        ]);

        $this->assertSame(
            ['$project' => ['name' => 1, 'customField' => '$foo']],
            $out
        );
    }

    public function testEmptyPipelineReturnsEmptyArray(): void
    {
        $this->assertSame([], QueryBuilder::getAggregateForNewParseVersion([]));
    }

    public function testSkipAndLimitStagesArePrefixed(): void
    {
        $out = QueryBuilder::getAggregateForNewParseVersion([
            'sort' => ['createdAt' => -1],
            'skip' => 20,
            'limit' => 10,
        ]);

        $this->assertSame(
            ['$sort' => ['createdAt' => -1], '$skip' => 20, '$limit' => 10],
            $out
        );
    }

    public function testCountStageIsPrefixed(): void
    {
        $out = QueryBuilder::getAggregateForNewParseVersion([
            'count' => 'total',
        ]);

        $this->assertSame(['$count' => 'total'], $out);
    }

    /**
     * A field named like a stage and produced as a $group output (e.g. "count")
     * must NOT be turned into a stage operator ("$count").
     */
    public function testStageNamedOutputFieldInGroupIsPreserved(): void
    {
        $out = QueryBuilder::getAggregateForNewParseVersion([
            'group' => [
                'objectId' => '$category',
                'count' => ['$sum' => 1],
                'limit' => ['$max' => '$value'],
            ],
        ]);

        $this->assertSame(
            ['$group' => [
                '_id' => '$category',
                'count' => ['$sum' => 1],
                'limit' => ['$max' => '$value'],
            ]],
            $out
        );
    }

    /**
     * objectId used as a value reference (e.g. "$factures.objectId") is a scalar,
     * not a key, and must be left untouched by the key-renaming rules.
     */
    public function testObjectIdAsValueReferenceIsPreserved(): void
    {
        $out = QueryBuilder::getAggregateForNewParseVersion([
            'unwind' => '$factures',
            'group' => ['objectId' => '$factures.objectId'],
        ]);

        $this->assertSame(
            ['$unwind' => '$factures', '$group' => ['_id' => '$factures.objectId']],
            $out
        );
    }

    public function testListFormPipelineIsTransformed(): void
    {
        $out = QueryBuilder::getAggregateForNewParseVersion([
            ['match' => ['status' => 'active']],
            ['group' => ['objectId' => '$type', 'count' => ['$sum' => 1]]],
        ]);

        $this->assertSame(
            [
                ['$match' => ['status' => 'active']],
                ['$group' => ['_id' => '$type', 'count' => ['$sum' => 1]]],
            ],
            $out
        );
    }
}
