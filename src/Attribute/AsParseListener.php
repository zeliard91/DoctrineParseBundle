<?php

declare(strict_types=1);

namespace Redking\ParseBundle\Attribute;

use Attribute;

/**
 * Service tag to autoconfigure Doctrine Parse event listeners.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AsParseListener
{
    public function __construct(
        public ?string $event = null,
        public ?string $connection = null,
        public ?int $priority = null,
    ) {
    }
}
