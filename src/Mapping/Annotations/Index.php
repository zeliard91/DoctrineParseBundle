<?php

declare(strict_types=1);

namespace Redking\ParseBundle\Mapping\Annotations;

use Attribute;

/**
 * Declares an index, either on a class or on a single property.
 *
 * @Annotation
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class Index extends AbstractIndex
{
}
