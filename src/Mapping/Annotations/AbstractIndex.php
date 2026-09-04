<?php

declare(strict_types=1);

namespace Redking\ParseBundle\Mapping\Annotations;

/**
 * Base class for index declarations.
 *
 * Parse only stores the MongoDB key specification of an index, so no option
 * such as "unique", "sparse", "expireAfterSeconds" or "partialFilterExpression"
 * is exposed here: the Parse schema API is unable to carry them.
 */
abstract class AbstractIndex implements Annotation
{
    /**
     * @param array<string, string|int> $keys  Field name => order
     * @param string|null               $name  Index name, generated from the keys when omitted
     * @param string|int|null           $order Order used when the index is declared on a property
     */
    public function __construct(
        public array $keys = [],
        public ?string $name = null,
        public string|int|null $order = null,
    ) {
    }
}
