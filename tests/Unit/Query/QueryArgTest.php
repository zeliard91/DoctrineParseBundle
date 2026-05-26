<?php

namespace Redking\ParseBundle\Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\Query\QueryArg;

enum QueryArgColor: string
{
    case Red = 'red';
    case Blue = 'blue';
}

/**
 * Unit coverage for QueryArg, the value wrapper used by every query criterion.
 * It normalizes values at construction time: dates to UTC, backed enums to
 * their scalar value, and iterables element-by-element.
 */
class QueryArgTest extends TestCase
{
    public function testPlainValueIsStoredAsIs(): void
    {
        $arg = new QueryArg('hello');

        $this->assertSame('hello', $arg->getValue());
        $this->assertNull($arg->getArgument());
    }

    public function testArgumentIsStored(): void
    {
        $arg = new QueryArg('pattern', 'i');

        $this->assertSame('i', $arg->getArgument());
    }

    public function testDateTimeIsConvertedToUtcWithoutMutatingTheOriginal(): void
    {
        $original = new \DateTime('2020-06-15 12:00:00', new \DateTimeZone('Europe/Paris'));
        $instant = $original->format('U');

        $arg = new QueryArg($original);

        $this->assertInstanceOf(\DateTime::class, $arg->getValue());
        $this->assertSame('UTC', $arg->getValue()->getTimezone()->getName(), 'value must be normalized to UTC');
        $this->assertSame($instant, $arg->getValue()->format('U'), 'the instant in time must be preserved');
        $this->assertSame('Europe/Paris', $original->getTimezone()->getName(), 'the original DateTime must not be mutated');
    }

    public function testDateTimeImmutableIsConvertedToUtc(): void
    {
        $original = new \DateTimeImmutable('2020-06-15 12:00:00', new \DateTimeZone('Europe/Paris'));

        $arg = new QueryArg($original);

        $this->assertInstanceOf(\DateTimeImmutable::class, $arg->getValue());
        $this->assertSame('UTC', $arg->getValue()->getTimezone()->getName());
        $this->assertSame($original->format('U'), $arg->getValue()->format('U'));
    }

    public function testBackedEnumIsStoredAsItsScalarValue(): void
    {
        $arg = new QueryArg(QueryArgColor::Red);

        $this->assertSame('red', $arg->getValue());
    }

    public function testIterableNormalizesBackedEnumElements(): void
    {
        $arg = new QueryArg([QueryArgColor::Red, 'plain', QueryArgColor::Blue]);

        $this->assertSame(['red', 'plain', 'blue'], $arg->getValue());
    }

    public function testIterableReindexesAndDropsKeys(): void
    {
        $arg = new QueryArg(['a' => 1, 'b' => 2]);

        $this->assertSame([1, 2], $arg->getValue(), 'iterables are flattened to a positional list');
    }

    public function testSettersAreFluentAndOverrideValues(): void
    {
        $arg = new QueryArg('initial');

        $this->assertSame($arg, $arg->setValue('changed'));
        $this->assertSame($arg, $arg->setArgument('m'));
        $this->assertSame('changed', $arg->getValue());
        $this->assertSame('m', $arg->getArgument());
    }
}
