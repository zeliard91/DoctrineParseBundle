<?php

namespace Redking\ParseBundle\Mapping\Annotations;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Identifies a class as a Parse object that can be stored in the database
 *
 * @Annotation
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ParseObject extends AbstractParseObject
{
    /** @var string|null */
    public $collection;

    /** @var string|null */
    public $repositoryClass;

    /** @var Index[] */
    public $indexes;

    /** @var bool */
    public $readOnly;

    /**
     * @param string|array{name: string, capped?: bool, size?: int, max?: int}|null $collection
     * @param Index[]                                                               $indexes
     */
    public function __construct(
        string $collection = null,
        ?string $repositoryClass = null,
        array $indexes = [],
        bool $readOnly = false,
    ) {
        $this->collection      = $collection;
        $this->repositoryClass = $repositoryClass;
        $this->indexes         = $indexes;
        $this->readOnly        = $readOnly;}
}