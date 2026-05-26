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
}
