<?php

declare(strict_types=1);

namespace Redking\ParseBundle\Mapping\Annotations;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Specifies a parent class that other objects may extend to inherit mapping
 * information
 *
 * @Annotation
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class MappedSuperclass extends AbstractParseObject
{
    /** @var string|null */
    public $repositoryClass;

    public function __construct(?string $repositoryClass = null)
    {
        $this->repositoryClass = $repositoryClass;
    }
}
