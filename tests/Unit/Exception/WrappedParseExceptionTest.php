<?php

namespace Redking\ParseBundle\Tests\Unit\Exception;

use Parse\ParseAggregateException;
use Parse\ParseException;
use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\Exception\WrappedParseException;

/**
 * Reproduces the bug: "Warning: Undefined array key 'object'" in WrappedParseException.
 *
 * Root cause: ParseObject::destroyBatch() builds error arrays WITHOUT an 'object' key:
 *   $errors[] = ['error' => $error, 'code' => $code];
 *
 * But WrappedParseException::__construct() accesses $error['object'] unconditionally.
 *
 * This manifests during bulk deletions (om->flush() after many om->remove() calls)
 * when Parse API returns errors from destroyAll().
 */
class WrappedParseExceptionTest extends TestCase
{
    /**
     * Reproduces the exact error structure from ParseObject::destroyBatch().
     * Without the fix, this throws: "Warning: Undefined array key 'object'"
     */
    public function testWrapsAggregateExceptionWithoutObjectKey(): void
    {
        // Exact error structure built by ParseObject::destroyBatch()
        $errors = [
            ['error' => 'Object not found.', 'code' => 101],
            ['error' => 'Internal server error.', 'code' => 1],
        ];

        $parseException = new ParseAggregateException('Errors during batch destroy.', $errors);

        // Must NOT throw "Undefined array key 'object'"
        $wrapped = new WrappedParseException($parseException);

        $this->assertInstanceOf(WrappedParseException::class, $wrapped);
        $this->assertStringContainsString('Object not found.', $wrapped->getMessage());
        $this->assertStringContainsString('Internal server error.', $wrapped->getMessage());
        $this->assertSame($parseException, $wrapped->getPrevious());
        $this->assertSame(502, $wrapped->getStatusCode());
    }

    /**
     * A simple (non-aggregate) ParseException should still work as before.
     */
    public function testWrapsSimpleParseException(): void
    {
        $parseException = new ParseException('Something went wrong', 100);
        $wrapped = new WrappedParseException($parseException);

        $this->assertSame(502, $wrapped->getStatusCode());
        $this->assertStringContainsString('Something went wrong', $wrapped->getMessage());
        $this->assertSame($parseException, $wrapped->getPrevious());
    }
}
